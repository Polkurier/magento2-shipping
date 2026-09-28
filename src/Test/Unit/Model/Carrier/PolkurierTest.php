<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Carrier;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierConfigs;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Pricing\Fixed;
use Polkurier\Shipping\Model\Pricing\PriceCalculatorPool;
use Polkurier\Shipping\Model\Pricing\Tiers;
use Polkurier\Shipping\Model\Shipment\NativeLabel;
use Polkurier\Shipping\Model\Shipment\TrackingLookup;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Testing\RecordingClient;
use Psr\Log\NullLogger;

#[CoversClass(Polkurier::class)]
#[CoversClass(CarrierCatalog::class)]
#[CoversClass(CarrierFeatures::class)]
class PolkurierTest extends SdkTestCase
{
    private RecordingClient $http;
    private bool $active = true;

    protected function setUp(): void
    {
        $this->http = new RecordingClient(self::fixture('available_carriers.json'));
    }

    public function testOffersTheMerchantsCarriersAtTheMerchantsPrices(): void
    {
        $methods = $this->rates($this->request('PL', 120.0, 2.0));

        self::assertSame(['INPOST_PACZKOMAT' => 12.99, 'DPD' => 14.99], $methods);
        $payload = $this->http->lastPayload();
        self::assertSame('available_carriers', $payload['apimethod']);
        self::assertTrue($payload['data']['additional_data']);
        self::assertSame(self::LOGIN, $payload['authorization']['login']);
    }

    public function testTiersFollowTheCart(): void
    {
        self::assertSame(['INPOST_PACZKOMAT' => 12.99, 'DPD' => 0.0], $this->rates($this->request('PL', 300.0, 2.0)));
    }

    public function testAbroadOnlyCarriersThatShipAbroad(): void
    {
        self::assertSame(['DPD' => 14.99], $this->rates($this->request('DE', 120.0, 2.0)));
    }

    public function testFreeShippingZeroesThePrice(): void
    {
        $request = $this->request('PL', 120.0, 2.0);
        $request->setFreeShipping(true);

        self::assertSame(['INPOST_PACZKOMAT' => 0.0, 'DPD' => 0.0], $this->rates($request));
    }

    public function testCarrierTheAccountNoLongerOffersIsHidden(): void
    {
        $this->http->willAnswerWith(self::success([
            ['servicecode' => 'DPD', 'name' => 'DPD Classic', 'foreign_shipments' => true],
        ]));

        self::assertSame(['DPD' => 14.99], $this->rates($this->request('PL', 120.0, 2.0)));
    }

    public function testAnUnreachableApiStillOffersDomesticMethodsAndNeverThrows(): void
    {
        $this->http->willAnswerWith('<html>Bad gateway</html>');

        self::assertSame(['INPOST_PACZKOMAT' => 12.99, 'DPD' => 14.99], $this->rates($this->request('PL', 120.0, 2.0)));
        self::assertSame([], $this->rates($this->request('DE', 120.0, 2.0)));
    }

    public function testInactiveCarrierOffersNothing(): void
    {
        $this->active = false;

        self::assertFalse($this->carrier()->collectRates($this->request('PL', 120.0, 2.0)));
        self::assertSame(0, $this->http->requestCount());
    }

    /**
     * @return array<string, float>|array{}
     */
    private function rates(RateRequest $request): array
    {
        $result = $this->carrier()->collectRates($request);
        self::assertInstanceOf(Result::class, $result);
        $methods = [];
        foreach ($result->getAllRates() as $rate) {
            self::assertSame(Polkurier::CODE, $rate->getData('carrier'));
            self::assertSame('', $rate->getData('carrier_title'), 'The carrier name never reaches the storefront.');
            $methods[(string)$rate->getData('method')] = (float)$rate->getData('price');
        }

        return $methods;
    }

    private function request(string $country, float $subtotal, float $weight): RateRequest
    {
        return new RateRequest([
            'dest_country_id' => $country,
            'package_value_with_discount' => $subtotal,
            'package_weight' => $weight,
            'store_id' => 1,
            'website_id' => 1,
        ]);
    }

    private function carrier(): Polkurier
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(fn(string $path): bool => $path === 'carriers/polkurier/active' && $this->active);
        $scopeConfig->method('getValue')->willReturnCallback(
            fn(string $path): mixed => match ($path) {
                'carriers/polkurier/active' => $this->active ? '1' : '0',
                'carriers/polkurier/title' => 'Polkurier',
                default => null,
            },
        );

        $configs = $this->createStub(CarrierConfigs::class);
        $configs->method('getActive')->willReturn([
            'INPOST_PACZKOMAT' => $this->config('INPOST_PACZKOMAT', 'Paczkomat', 'FIXED', ['price' => 12.99]),
            'DPD' => $this->config('DPD', 'Kurier DPD', 'TIER_SUBTOTAL', ['tiers' => [
                ['subtotal_from' => 0, 'price' => 14.99],
                ['subtotal_from' => 250, 'price' => 0],
            ]]),
        ]);

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $catalog = new CarrierCatalog(
            $this->clientPool($this->http),
            new Config($scopeConfig, $this->createStub(EncryptorInterface::class)),
            $cache,
        );

        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturnCallback(
            fn(): Result => new Result($this->createStub(StoreManagerInterface::class)),
        );
        $methodFactory = $this->createStub(MethodFactory::class);
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('round')->willReturnArgument(0);
        $methodFactory->method('create')->willReturnCallback(static fn(): Method => new Method($priceCurrency));

        return new Polkurier(
            $scopeConfig,
            $this->createStub(ErrorFactory::class),
            new NullLogger(),
            $resultFactory,
            $methodFactory,
            $this->createStub(StatusFactory::class),
            $configs,
            $catalog,
            new CarrierFeatures(),
            new PriceCalculatorPool([
                'FIXED' => new Fixed(),
                'TIER_SUBTOTAL' => new Tiers(true, false),
            ]),
            $this->createStub(TrackingLookup::class),
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(NativeLabel::class),
        );
    }

    /**
     * @param array<string, mixed> $priceInfo
     */
    private function config(string $code, string $label, string $model, array $priceInfo): CarrierConfigInterface
    {
        return $this->createConfiguredStub(CarrierConfigInterface::class, [
            'getCarrierCode' => $code,
            'getCarrierName' => $code,
            'getLabelForStore' => $label,
            'isActive' => true,
            'isAvailable' => true,
            'getPricingModel' => $model,
            'getPriceInfo' => $priceInfo,
        ]);
    }
}
