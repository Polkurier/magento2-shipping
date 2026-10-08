<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Observer\Checkout;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\QuotePointInterface;
use Polkurier\Shipping\Api\QuotePointRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Observer\Checkout\RestrictCashOnDeliveryObserver;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Response\Carrier;

#[CoversClass(RestrictCashOnDeliveryObserver::class)]
#[AllowMockObjectsWithoutExpectations]
class RestrictCashOnDeliveryObserverTest extends TestCase
{
    /**
     * @var array<string, bool> whether each carrier collects cash, by code; left out: not on the account
     */
    private array $collects = ['DPD' => true, 'INPOST_PACZKOMAT' => true, 'UPS' => false];

    /**
     * @var array<int, array{string, bool|null}> the point chosen for each quote address: its method and
     *     whether it takes cash (null: not known)
     */
    private array $points = [];

    private bool $outage = false;

    /**
     * @var string[]
     */
    private array $warnings = [];

    public function testACarrierThatCollectsKeepsCashOnDelivery(): void
    {
        self::assertTrue($this->available('cashondelivery', ['polkurier_DPD']));
    }

    public function testACarrierThatDoesntHidesIt(): void
    {
        self::assertFalse($this->available('cashondelivery', ['polkurier_UPS']));
    }

    public function testACarrierNoLongerOnTheAccountHidesIt(): void
    {
        self::assertFalse($this->available('cashondelivery', ['polkurier_GLS']));
    }

    public function testAPointThatDoesntTakeCashTurnsItOff(): void
    {
        $this->points[0] = ['INPOST_PACZKOMAT', false];

        self::assertFalse($this->available('cashondelivery', ['polkurier_INPOST_PACZKOMAT']));
    }

    public function testAPointNotKnownToTakeCashIsTakenTo(): void
    {
        $this->points[0] = ['INPOST_PACZKOMAT', null];

        self::assertTrue($this->available('cashondelivery', ['polkurier_INPOST_PACZKOMAT']));
    }

    public function testAPointOfAnotherMethodDoesntCount(): void
    {
        // Chosen for the parcel lockers, then the customer went for the courier.
        $this->points[0] = ['INPOST_PACZKOMAT', false];

        self::assertTrue($this->available('cashondelivery', ['polkurier_DPD']));
    }

    public function testOtherPaymentMethodsAndCarriersAreLeftAlone(): void
    {
        self::assertTrue($this->available('checkmo', ['polkurier_UPS']));
        self::assertTrue($this->available('cashondelivery', ['flatrate_flatrate']));
    }

    public function testAMethodHiddenAlreadyStaysHidden(): void
    {
        self::assertFalse($this->available('cashondelivery', ['polkurier_DPD'], false));
    }

    public function testWhenPolkurierCantBeAskedPaymentIsLeftAsItIs(): void
    {
        $this->outage = true;

        self::assertTrue($this->available('cashondelivery', ['polkurier_UPS']));
        self::assertCount(1, $this->warnings);
    }

    public function testInMultiAddressCheckoutEveryPolkurierAddressMustCollect(): void
    {
        self::assertTrue(
            $this->available('cashondelivery', [11 => 'polkurier_DPD', 12 => 'flatrate_flatrate'], multi: true),
        );
        self::assertFalse(
            $this->available('cashondelivery', [11 => 'polkurier_DPD', 12 => 'polkurier_UPS'], multi: true),
        );
    }

    public function testInMultiAddressCheckoutEachAddressHasItsOwnPoint(): void
    {
        $this->points[12] = ['INPOST_PACZKOMAT', false];

        self::assertFalse(
            $this->available('cashondelivery', [11 => 'polkurier_DPD', 12 => 'polkurier_INPOST_PACZKOMAT'], multi: true),
        );
        self::assertTrue(
            $this->available('cashondelivery', [11 => 'polkurier_INPOST_PACZKOMAT', 13 => 'polkurier_DPD'], multi: true),
        );
    }

    public function testInMultiAddressCheckoutACarrierThatCantCollectHidesItWhateverThePoints(): void
    {
        $this->points[11] = ['INPOST_PACZKOMAT', false];

        self::assertFalse(
            $this->available('cashondelivery', [11 => 'polkurier_INPOST_PACZKOMAT', 12 => 'polkurier_UPS'], multi: true),
        );
    }

    /**
     * @param array<int, string> $methods the shipping method of each address, by quote address id
     */
    private function available(string $payment, array $methods, bool $before = true, bool $multi = false): bool
    {
        $addresses = [];
        foreach ($methods as $id => $method) {
            $address = $this->getMockBuilder(Address::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getShippingMethod', 'getId'])
                ->getMock();
            $address->method('getShippingMethod')->willReturn($method);
            $address->method('getId')->willReturn($id);
            $addresses[] = $address;
        }
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getAllShippingAddresses', 'getId', 'getStoreId'])->getMock();
        $quote->method('getShippingAddress')->willReturn($addresses[0]);
        $quote->method('getAllShippingAddresses')->willReturn($addresses);
        $quote->method('getId')->willReturn(5);
        $quote->method('getStoreId')->willReturn(1);
        $quote->setData('is_multi_shipping', $multi);
        $method = $this->createStub(MethodInterface::class);
        $method->method('getCode')->willReturn($payment);
        $result = new DataObject(['is_available' => $before]);

        $this->observer()->execute(new Observer(['event' => new Event([
            'result' => $result,
            'method_instance' => $method,
            'quote' => $quote,
        ])]));

        return (bool)$result->getData('is_available');
    }

    private function observer(): RestrictCashOnDeliveryObserver
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getCodPaymentMethods')->willReturn(['cashondelivery']);
        $catalog = $this->createStub(CarrierCatalog::class);
        $catalog->method('getCarrier')->willReturnCallback(function (string $code): ?Carrier {
            if ($this->outage) {
                throw new ConnectionException('Timed out');
            }

            return isset($this->collects[$code]) ? new Carrier($code, $code, false) : null;
        });
        $features = $this->createStub(CarrierFeatureResolver::class);
        $features->method('isCodAvailable')
            ->willReturnCallback(fn(Carrier $carrier): bool => $this->collects[$carrier->getServiceCode()]);
        $store = $this->createStub(QuotePointRepositoryInterface::class);
        $store->method('getForQuote')
            ->willReturnCallback(function (int $quoteId, int $addressId): ?QuotePointInterface {
                if (!isset($this->points[$addressId])) {
                    return null;
                }
                $point = $this->createStub(QuotePointInterface::class);
                $point->method('getMethodCode')->willReturn($this->points[$addressId][0]);
                $point->method('getPointCod')->willReturn($this->points[$addressId][1]);

                return $point;
            });
        $logger = $this->createStub(Logger::class);
        $logger->method('warning')->willReturnCallback(function (string|\Stringable $message): void {
            $this->warnings[] = (string)$message;
        });

        return new RestrictCashOnDeliveryObserver($config, $catalog, $features, $store, $logger);
    }
}
