<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\PickupPoint;

use Magento\Checkout\Model\Session;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\QuotePointRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\PickupPoint\MapConfigProvider;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;
use Polkurier\Shipping\Model\QuotePoint;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\Carrier\AdditionalData;
use Polkurier\Webservice\Response\Carrier\AdditionalData\PointsMap;

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

    public function testWithoutATokenThePageAsksForOneWhenTheMapOpens(): void
    {
        $config = $this->provider('')->getConfig()['pickupPoints'];

        self::assertSame(
            ['', 0, 'https://shop.test/pickup-points/map/token/'],
            [$config['token'], $config['tokenExpires'], $config['tokenUrl']],
        );
        self::assertTrue(
            ((array)$config['methods'])['INPOST_PACZKOMAT']['required'],
            'Still asked for: the methods are offered only with a map.',
        );
    }

    public function testTheTokenComesWithWhenItRunsOut(): void
    {
        self::assertSame(1790014400, $this->provider('map-token')->getConfig()['pickupPoints']['tokenExpires']);
    }

    public function testTheChosenPointHasOneShapeForEveryCheckout(): void
    {
        $point = $this->createStub(QuotePoint::class);
        $point->method('getMethodCode')->willReturn('INPOST_PACZKOMAT');
        $point->method('getPointId')->willReturn('WAW195M');
        $point->method('getPointLabel')->willReturn('Krótka 5, Warszawa');
        $point->method('getPointCod')->willReturn(false);

        self::assertSame(
            ['method' => 'INPOST_PACZKOMAT', 'id' => 'WAW195M', 'label' => 'Krótka 5, Warszawa', 'cod' => false],
            $this->provider('map-token')->getMapConfig($point)['selected'],
        );
        self::assertNull($this->provider('map-token')->getMapConfig(null)['selected']);
    }

    private function provider(string $token): MapConfigProvider
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isActive')->willReturn(true);
        $config->method('getMapPointFunctions')->willReturn([]);
        $carrierConfigs = $this->createStub(CarrierConfigProvider::class);
        $carrierConfigs->method('getActive')
            ->willReturn(['INPOST_PACZKOMAT' => $this->createStub(CarrierConfigInterface::class)]);
        $catalog = $this->createStub(CarrierCatalog::class);
        $catalog->method('getCarrier')
            ->willReturn(new Carrier('INPOST_PACZKOMAT', 'Paczkomat', false, new AdditionalData(
                pointsMap: new PointsMap(['inpost'], recipientPoint: PointRequirementEnum::Required),
            )));
        $tokens = $this->createStub(MapTokenProvider::class);
        $tokens->method('getTokenWithExpiry')
            ->willReturn(['token' => $token, 'expires' => $token !== '' ? 1790014400 : 0]);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturn('https://shop.test/pickup-points/map/token/');
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new MapConfigProvider(
            $config,
            $carrierConfigs,
            $catalog,
            new CarrierFeatureResolver(),
            $tokens,
            $this->createStub(QuotePointRepositoryInterface::class),
            $this->createStub(Session::class),
            $storeManager,
            $url,
            $this->createStub(Logger::class),
        );
    }
}
