<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Carrier;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Tracking\Result\Status;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Carrier\AvailableCarrierFilter;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Carrier\Pricing\FixedPriceCalculator;
use Polkurier\Shipping\Model\Carrier\Pricing\PriceCalculatorPool;
use Polkurier\Shipping\Model\Carrier\Pricing\TieredPriceCalculator;
use Polkurier\Shipping\Model\Carrier\RateCollector;
use Polkurier\Shipping\Model\Carrier\WeightUnitConverter;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;
use Polkurier\Shipping\Model\Sdk\AccountCache;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Test\Unit\Support\MagentoMeasures;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Testing\RecordingClient;

#[CoversClass(Polkurier::class)]
#[CoversClass(RateCollector::class)]
#[CoversClass(CarrierCatalog::class)]
#[CoversClass(CarrierFeatureResolver::class)]
class PolkurierTest extends SdkTestCase
{
    private RecordingClient $http;
    private bool $active = true;
    private string $weightUnit = 'kgs';
    private string $dpdModel = 'TIER_SUBTOTAL';
    private string $area = 'frontend';

    /**
     * Shipments by waybill.
     *
     * @var array<string, ShipmentInterface>
     */
    private array $waybills = [];

    /**
     * The map token Polkurier gives, or its failure.
     */
    private string|\Throwable $mapToken = 'map-token';

    /**
     * @var array<int, array<string, int|float>>
     */
    private array $dpdTiers = [
        ['subtotal_from' => 0, 'price' => 14.99],
        ['subtotal_from' => 250, 'price' => 0],
    ];

    public function testOffersTheMerchantsCarriersAtTheMerchantsPrices(): void
    {
        $methods = $this->collectRates($this->request('PL', 120.0, 2.0));

        self::assertSame(['INPOST_PACZKOMAT' => 12.99, 'DPD' => 14.99], $methods);
        $payload = $this->http->lastPayload();
        self::assertSame('available_carriers', $payload['apimethod']);
        self::assertTrue($payload['data']['additional_data']);
        self::assertSame(self::LOGIN, $payload['authorization']['login']);
    }

    public function testTiersFollowTheCart(): void
    {
        self::assertSame(
            ['INPOST_PACZKOMAT' => 12.99, 'DPD' => 0.0],
            $this->collectRates($this->request('PL', 300.0, 2.0)),
        );
    }

    public function testCarriersForAbroadGoAbroadOnlyAndTheOthersOnlyAtHome(): void
    {
        // Polkurier prices neither a domestic carrier abroad nor one for abroad at home.
        $this->http->willAnswerWith(self::encodeSuccess([
            ['servicecode' => 'INPOST_PACZKOMAT', 'name' => 'InPost Paczkomat', 'foreign_shipments' => false],
            ['servicecode' => 'DPD', 'name' => 'DPD Export', 'foreign_shipments' => true],
        ]));

        self::assertSame(['DPD' => 14.99], $this->collectRates($this->request('DE', 120.0, 2.0)));
        self::assertSame(['INPOST_PACZKOMAT' => 12.99], $this->collectRates($this->request('PL', 120.0, 2.0)));
    }

    public function testFreeShippingZeroesThePrice(): void
    {
        $request = $this->request('PL', 120.0, 2.0);
        $request->setFreeShipping(true);

        self::assertSame(['INPOST_PACZKOMAT' => 0.0, 'DPD' => 0.0], $this->collectRates($request));
    }

    public function testFreeShippingDoesNotOfferAMethodTheCartIsNotPricedFor(): void
    {
        $this->dpdTiers = [['subtotal_from' => 50, 'price' => 14.99]];
        $request = $this->request('PL', 20.0, 2.0);
        $request->setFreeShipping(true);

        self::assertSame(['INPOST_PACZKOMAT' => 0.0], $this->collectRates($request));
    }

    public function testWeightTiersAreInKilogramsWhateverTheStoresUnit(): void
    {
        $this->weightUnit = 'lbs';
        $this->dpdTiers = [['weight_from' => 0, 'price' => 14.99], ['weight_from' => 5, 'price' => 25.0]];
        $this->dpdModel = 'TIER_WEIGHT';

        // 10 lb are 4.5 kg: still the first tier.
        self::assertSame(
            ['INPOST_PACZKOMAT' => 12.99, 'DPD' => 14.99],
            $this->collectRates($this->request('PL', 120.0, 10.0)),
        );
    }

    public function testCarrierTheAccountNoLongerOffersIsHidden(): void
    {
        $this->http->willAnswerWith(self::encodeSuccess([
            ['servicecode' => 'DPD', 'name' => 'DPD Classic', 'foreign_shipments' => false],
        ]));

        self::assertSame(['DPD' => 14.99], $this->collectRates($this->request('PL', 120.0, 2.0)));
    }

    public function testAnUnreachableApiStillOffersDomesticMethodsAndNeverThrows(): void
    {
        $this->http->willAnswerWith('<html>Bad gateway</html>');

        self::assertSame(
            ['INPOST_PACZKOMAT' => 12.99, 'DPD' => 14.99],
            $this->collectRates($this->request('PL', 120.0, 2.0)),
        );
        self::assertSame([], $this->collectRates($this->request('DE', 120.0, 2.0)));
    }

    public function testAPickupPointOnlyCarrierIsLeftOutWhileTheMapCantOpen(): void
    {
        foreach (['', new ConnectionException('Timed out.')] as $token) {
            $this->mapToken = $token;

            self::assertSame(['DPD' => 14.99], $this->collectRates($this->request('PL', 120.0, 2.0)));
        }
    }

    public function testTheAdminKeepsAPickupPointOnlyCarrierWithoutTheMap(): void
    {
        $this->mapToken = '';
        $this->area = Area::AREA_ADMINHTML;

        self::assertSame(
            ['INPOST_PACZKOMAT' => 12.99, 'DPD' => 14.99],
            $this->collectRates($this->request('PL', 120.0, 2.0)),
        );
    }

    public function testTrackingIsTitledByTheMethodAndLinksToTheCarriersPage(): void
    {
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getTrackingUrl')->willReturn('https://track.example/WB-1');
        $shipment->method('getCarrierCode')->willReturn('DPD');
        $shipment->method('getStoreId')->willReturn(1);
        $this->waybills = ['WB-1' => $shipment];

        $known = $this->createCarrier()->getTrackingInfo('WB-1');
        $unknown = $this->createCarrier()->getTrackingInfo('WB-9');

        self::assertSame(['polkurier', 'Kurier DPD', 'WB-1', 'https://track.example/WB-1'], [
            $known->getData('carrier'),
            $known->getData('carrier_title'),
            $known->getData('tracking'),
            $known->getData('url'),
        ]);
        self::assertSame(
            ['', ''],
            [$unknown->getData('carrier_title'), $unknown->getData('url')],
            'A track of an unknown waybill has no carrier title or URL.',
        );
    }

    public function testInactiveCarrierOffersNothing(): void
    {
        $this->active = false;

        self::assertFalse($this->createCarrier()->collectRates($this->request('PL', 120.0, 2.0)));
        self::assertSame(0, $this->http->requestCount());
    }

    protected function setUp(): void
    {
        $this->http = new RecordingClient(self::loadFixture('available_carriers.json'));
    }

    /**
     * @return array<string, float>|array{}
     */
    private function collectRates(RateRequest $request): array
    {
        $result = $this->createCarrier()->collectRates($request);
        self::assertInstanceOf(Result::class, $result);
        $methods = [];
        foreach ($result->getAllRates() as $rate) {
            self::assertSame(Polkurier::CODE, $rate->getData('carrier'));
            self::assertSame('', $rate->getData('carrier_title'), 'The rate has no carrier title.');
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

    private function createCarrier(): Polkurier
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')
            ->willReturnCallback(fn(string $path): bool => $path === 'carriers/polkurier/active' && $this->active);
        $scopeConfig->method('getValue')->willReturnCallback(
            fn(string $path): mixed => match ($path) {
                'carriers/polkurier/active' => $this->active ? '1' : '0',
                'carriers/polkurier/title' => 'Polkurier',
                'general/locale/weight_unit' => $this->weightUnit,
                default => null,
            },
        );

        $configs = $this->createStub(CarrierConfigProvider::class);
        $configs->method('getActive')->willReturn([
            'INPOST_PACZKOMAT' => $this->createConfig('INPOST_PACZKOMAT', 'Paczkomat', 'FIXED', ['price' => 12.99]),
            'DPD' => $this->createConfig('DPD', 'Kurier DPD', $this->dpdModel, ['tiers' => $this->dpdTiers]),
        ]);
        $configs->method('getLabel')
            ->willReturnCallback(static fn(string $code): ?string => $code === 'DPD' ? 'Kurier DPD' : null);

        $cache = $this->createStub(PolkurierCache::class);
        $cache->method('load')->willReturn(false);
        $config = new ConfigProvider($scopeConfig, $this->createStub(EncryptorInterface::class));
        $catalog = new CarrierCatalog(
            $this->createClientPool($this->http),
            $cache,
            new ErrorMessageResolver(),
            new AvailableCarrierFilter($config),
            new AccountCache($this->createStub(PolkurierCache::class), new Json(), $config),
        );

        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturnCallback(
            fn(): Result => new Result($this->createStub(StoreManagerInterface::class)),
        );
        $methodFactory = $this->createStub(MethodFactory::class);
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('round')->willReturnArgument(0);
        $methodFactory->method('create')->willReturnCallback(static fn(): Method => new Method($priceCurrency));

        $tokens = $this->createStub(MapTokenProvider::class);
        $tokens->method('getToken')->willReturnCallback(function (): string {
            if ($this->mapToken instanceof \Throwable) {
                throw $this->mapToken;
            }

            return $this->mapToken;
        });
        $appState = $this->createStub(State::class);
        $appState->method('getAreaCode')->willReturnCallback(fn(): string => $this->area);

        $rateCollector = new RateCollector(
            $resultFactory,
            $methodFactory,
            $configs,
            $catalog,
            new CarrierFeatureResolver(),
            new PriceCalculatorPool($this->createStub(Logger::class), [
                'FIXED' => new FixedPriceCalculator(),
                'TIER_SUBTOTAL' => new TieredPriceCalculator(true, false),
                'TIER_WEIGHT' => new TieredPriceCalculator(false, true),
            ]),
            new WeightUnitConverter($scopeConfig, MagentoMeasures::createCarrierHelper()),
            $this->createStub(Logger::class),
            $tokens,
            $appState,
            $config,
            $scopeConfig,
        );

        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('findLatestByWaybill')
            ->willReturnCallback(fn(string $waybill): ?ShipmentInterface => $this->waybills[$waybill] ?? null);

        $statuses = $this->createStub(StatusFactory::class);
        $statuses->method('create')->willReturnCallback(static fn(): Status => new Status());

        return new Polkurier(
            $scopeConfig,
            $this->createStub(ErrorFactory::class),
            $this->createStub(Logger::class),
            $rateCollector,
            $statuses,
            $finder,
            $configs,
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(NativeLabelService::class),
            new PackType(),
        );
    }

    /**
     * @param array<string, mixed> $priceInfo
     */
    private function createConfig(string $code, string $label, string $model, array $priceInfo): CarrierConfigInterface
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
