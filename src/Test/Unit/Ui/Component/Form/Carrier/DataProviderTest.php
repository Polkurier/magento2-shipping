<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Form\Carrier;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Carrier\CarrierLogoStorage;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\PickupPoint\AdminMapConfigProvider;
use Polkurier\Shipping\Model\PickupPoint\DefaultSenderPointManager;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;
use Polkurier\Shipping\Model\PickupPoint\PointFinder;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\Collection;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\CollectionFactory;
use Polkurier\Shipping\Ui\Component\Form\Carrier\DataProvider;
use Polkurier\Webservice\Dto\Response\Carrier;
use Polkurier\Webservice\Dto\Response\Carrier\AdditionalData;
use Polkurier\Webservice\Dto\Response\Carrier\AdditionalData\PointsMap;
use Polkurier\Webservice\Dto\Response\CourierPoint;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Exception\ConnectionException;

#[CoversClass(DataProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class DataProviderTest extends TestCase
{
    private const string CODE = 'UPS_AP_POINT_POINT';

    public function testACarrierWithADefaultHasItChosenOnItsOwnPointsNearTheDefaultSender(): void
    {
        $provider = $this->createProvider('U02842068');

        $field = $provider->getMeta()['general']['children'][DataProvider::FIELD_DEFAULT_SENDER_POINT]['arguments']
            ['data']['config'];
        $data = $provider->getData()[7];

        self::assertTrue($field['visible']);
        self::assertSame(
            ['baseUrl' => 'https://maps.example/', 'tokenUrl' => 'polkurier/shipment/mapToken'],
            $field['map'],
        );
        self::assertSame(['ups'], $field['providers']);
        self::assertSame('00-001', $field['postcode']);
        self::assertEquals(
            (object)['U02842068' => ['name' => 'Żabka', 'label' => 'Krótka 5, 00-001 Warszawa']],
            $field['pointDetails'],
        );
        self::assertSame(
            ['U02842068', 'U02842068'],
            [$data[DataProvider::FIELD_DEFAULT_SENDER_POINT], $data[DataProvider::FIELD_DEFAULT_SENDER_POINT_BEFORE]],
            'Saving sends it only when it was changed.',
        );
    }

    public function testACarrierWithoutOneSetStartsEmpty(): void
    {
        $provider = $this->createProvider('');

        $field = $provider->getMeta()['general']['children'][DataProvider::FIELD_DEFAULT_SENDER_POINT]['arguments']
            ['data']['config'];

        self::assertTrue($field['visible']);
        self::assertEquals((object)[], $field['pointDetails']);
        self::assertSame('', $provider->getData()[7][DataProvider::FIELD_DEFAULT_SENDER_POINT]);
    }

    public function testACarrierThatCantHaveOneHasNoField(): void
    {
        $meta = $this->createProvider(null)->getMeta();

        self::assertArrayNotHasKey(DataProvider::FIELD_DEFAULT_SENDER_POINT, $meta['general']['children'] ?? []);
    }

    public function testWhenTheCarriersCantBeReadThereIsNoField(): void
    {
        $meta = $this->createProvider('U02842068', new ConnectionException('Timed out'))->getMeta();

        self::assertArrayNotHasKey(DataProvider::FIELD_DEFAULT_SENDER_POINT, $meta['general']['children'] ?? []);
    }

    /**
     * @param string|null $point the carrier's default, or null when it can't have one
     */
    private function createProvider(?string $point, ?\Throwable $carriersFailure = null): DataProvider
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['id', null, '7']]);
        $config = $this->createStub(CarrierConfigInterface::class);
        $config->method('getCarrierCode')->willReturn(self::CODE);
        $config->method('getPriceInfo')->willReturn([]);
        $config->method('getStoreLabels')->willReturn([]);
        $carriers = $this->createStub(Collection::class);
        $carriers->method('getItems')->willReturn([$config]);
        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturn($carriers);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStores')->willReturn([]);

        $catalog = $this->createStub(CarrierCatalog::class);
        $carrier = new Carrier(self::CODE, 'UPS Access Point', false, new AdditionalData(
            pointsMap: new PointsMap(['ups'], senderPoint: PointRequirementEnum::Required),
        ));
        if ($carriersFailure !== null) {
            $catalog->method('getCarrier')->willThrowException($carriersFailure);
        } else {
            $catalog->method('getCarrier')->willReturn($carrier);
        }
        $defaults = $this->createStub(DefaultSenderPointManager::class);
        $defaults->method('getForCarrier')->willReturn($point);
        $defaults->method('get')->willReturn($point !== null ? [self::CODE => $point] : []);
        $finder = $this->createStub(PointFinder::class);
        $finder->method('find')->willReturnCallback(
            static fn(string $id): ?CourierPoint => $id === '' ? null : new CourierPoint($id),
        );
        $finder->method('describe')->willReturn(['name' => 'Żabka', 'label' => 'Krótka 5, 00-001 Warszawa']);
        $sender = $this->createStub(AddressInterface::class);
        $sender->method('getPostcode')->willReturn('00-001');
        $addresses = $this->createStub(AddressRepositoryInterface::class);
        $addresses->method('getDefaultSenderAddress')->willReturn($sender);
        $settings = $this->createStub(ConfigProvider::class);
        $settings->method('getMapUrl')->willReturn('https://maps.example');
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnArgument(0);
        $maps = new AdminMapConfigProvider($settings, $url, $this->createStub(MapTokenProvider::class));

        return new DataProvider(
            'polkurier_carrier_form_data_source',
            'config_id',
            'id',
            $collections,
            $request,
            $stores,
            $this->createStub(CarrierLogoStorage::class),
            $this->createStub(DataPersistorInterface::class),
            $catalog,
            new CarrierFeatureResolver(),
            $defaults,
            $finder,
            $addresses,
            $maps,
        );
    }
}
