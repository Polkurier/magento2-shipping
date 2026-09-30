<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierConfigs;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Checkout\MapConfigProvider;
use Polkurier\Shipping\Model\Checkout\MapTokenProvider;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\QuotePoint;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\Carrier\AdditionalData;
use Polkurier\Webservice\Response\Carrier\AdditionalData\PointsMap;
use Psr\Log\NullLogger;

#[CoversClass(MapConfigProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class MapConfigProviderTest extends TestCase
{
    public function testAMethodNeedingAPointIsOfferedWithTheMapsToken(): void
    {
        $config = $this->provider('map-token')->getConfig()['pickupPoints'];

        self::assertSame('map-token', $config['token']);
        self::assertTrue(((array)$config['methods'])['INPOST_PACZKOMAT']['required']);
    }

    public function testNoMethodAsksForAPointWithoutAToken(): void
    {
        $config = $this->provider('')->getConfig()['pickupPoints'];

        self::assertSame('', $config['token']);
        self::assertSame([], (array)$config['methods'], 'No map to choose from, so no point to require.');
    }

    public function testTheChosenPointHasOneShapeForEveryCheckout(): void
    {
        $point = $this->createStub(QuotePoint::class);
        $point->method('getMethodCode')->willReturn('INPOST_PACZKOMAT');
        $point->method('getPointId')->willReturn('WAW195M');
        $point->method('getPointLabel')->willReturn('Krótka 5, Warszawa');

        self::assertSame(
            ['method' => 'INPOST_PACZKOMAT', 'id' => 'WAW195M', 'label' => 'Krótka 5, Warszawa'],
            $this->provider('map-token')->getMapConfig($point)['selected'],
        );
        self::assertNull($this->provider('map-token')->getMapConfig(null)['selected']);
    }

    private function provider(string $token): MapConfigProvider
    {
        $config = $this->createStub(Config::class);
        $config->method('isActive')->willReturn(true);
        $config->method('getMapPointFunctions')->willReturn([]);
        $carrierConfigs = $this->createStub(CarrierConfigs::class);
        $carrierConfigs->method('getActive')->willReturn(['INPOST_PACZKOMAT' => $this->createStub(CarrierConfigInterface::class)]);
        $catalog = $this->createStub(CarrierCatalog::class);
        $catalog->method('getCarrier')->willReturn(new Carrier('INPOST_PACZKOMAT', 'Paczkomat', false, new AdditionalData(
            pointsMap: new PointsMap(['inpost'], recipientPoint: PointRequirementEnum::Required),
        )));
        $tokens = $this->createStub(MapTokenProvider::class);
        $tokens->method('getToken')->willReturn($token);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new MapConfigProvider(
            $config,
            $carrierConfigs,
            $catalog,
            new CarrierFeatures(),
            $tokens,
            $this->createStub(OrderPointStore::class),
            $this->createStub(Session::class),
            $storeManager,
            new NullLogger(),
        );
    }
}
