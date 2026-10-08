<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Form\Address;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sync\EntityHandlerInterface;
use Polkurier\Shipping\Ui\Component\Form\Address\DataProvider;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\Carrier\AdditionalData;
use Polkurier\Webservice\Response\Carrier\AdditionalData\PointsMap;

#[CoversClass(DataProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class DataProviderTest extends TestCase
{
    public function testTheDropOffMapShowsThePointsOfEveryCarrierTakingParcelsAtPoints(): void
    {
        $config = $this->pointConfig($this->provider([
            self::carrier('INPOST_PACZKOMAT', PointRequirementEnum::Optional, ['INPOST_PACZKOMAT']),
            self::carrier('UPS_AP_POINT_POINT', PointRequirementEnum::Required, ['UPS_AP', 'INPOST_PACZKOMAT']),
            self::carrier('DPD', PointRequirementEnum::None, ['DPD_PICKUP']),
        ]));

        self::assertSame(['INPOST_PACZKOMAT', 'UPS_AP'], $config['providers']);
        self::assertSame(
            ['baseUrl' => 'https://maps.polkurier.pl/', 'tokenUrl' => 'https://admin/polkurier/shipment/mapToken'],
            $config['map'],
        );
    }

    public function testWithoutTheCarrierListTheMapShowsEveryProvider(): void
    {
        $config = $this->pointConfig($this->provider(new PolkurierException('down')));

        self::assertSame([], $config['providers']);
    }

    /**
     * @return array<string, mixed>
     */
    private function pointConfig(DataProvider $provider): array
    {
        /** @var array<string, mixed> */
        return $provider->getMeta()['general']['children']['point_id']['arguments']['data']['config'];
    }

    /**
     * @param Carrier[]|PolkurierException $carriers
     */
    private function provider(array|PolkurierException $carriers): DataProvider
    {
        $catalog = $this->createStub(CarrierCatalog::class);
        if ($carriers instanceof PolkurierException) {
            $catalog->method('getCarriers')->willThrowException($carriers);
        } else {
            $catalog->method('getCarriers')->willReturn($carriers);
        }
        $configProvider = $this->createStub(ConfigProvider::class);
        $configProvider->method('getMapUrl')->willReturn('https://maps.polkurier.pl');
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn(string $route): string => 'https://admin/' . $route);

        return new DataProvider(
            'polkurier_address_form_data_source',
            'id',
            'id',
            $this->createStub(AbstractCollection::class),
            $this->createStub(RequestInterface::class),
            $this->createStub(DataPersistorInterface::class),
            $this->createStub(EntityHandlerInterface::class),
            $configProvider,
            $url,
            $catalog,
            new CarrierFeatureResolver(),
            $this->createStub(Logger::class),
        );
    }

    /**
     * @param string[] $providers
     */
    private static function carrier(string $code, PointRequirementEnum $senderPoint, array $providers): Carrier
    {
        return new Carrier($code, $code, false, new AdditionalData(
            pointsMap: new PointsMap(availableProviders: $providers, senderPoint: $senderPoint),
        ));
    }
}
