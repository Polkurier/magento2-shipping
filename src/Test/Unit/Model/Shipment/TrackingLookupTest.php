<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\CarrierConfigs;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\TrackingLookup;

#[CoversClass(TrackingLookup::class)]
#[AllowMockObjectsWithoutExpectations]
class TrackingLookupTest extends TestCase
{
    public function testTheShipmentIsKnownByItsMethodLabelInItsStore(): void
    {
        $config = $this->createStub(CarrierConfigInterface::class);
        $config->method('getLabelForStore')->willReturnCallback(static fn(int $storeId): string => $storeId === 2 ? 'Paczkomat 24/7' : 'Paczkomat');

        self::assertSame('Paczkomat 24/7', $this->lookup($config)->getMethodLabel($this->shipment()));
    }

    public function testACarrierNoLongerSetUpIsKnownByItsCode(): void
    {
        self::assertSame('INPOST_PACZKOMAT', $this->lookup(null)->getMethodLabel($this->shipment()));
    }

    public function testAWaybillLeadsToTheNewestShipmentThatCarriesIt(): void
    {
        $filters = [];
        $order = [];
        $found = $this->shipment()->setWaybills(['WB-1', 'WB-2']);
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(function (string $field, mixed $condition) use (&$filters, $collection): Collection {
            $filters[$field] = $condition;

            return $collection;
        });
        $collection->method('setOrder')->willReturnCallback(function (string $field, string $direction) use (&$order, $collection): Collection {
            $order = [$field, $direction];

            return $collection;
        });
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getItems')->willReturn([$found]);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $shipment = $this->lookup(null, $factory)->findByWaybill('WB-2');

        self::assertSame($found, $shipment);
        self::assertSame([ShipmentInterface::WAYBILLS => ['finset' => 'WB-2']], $filters, 'One waybill among the shipment\'s list.');
        self::assertSame([ShipmentInterface::ENTITY_ID, 'DESC'], $order, 'A replacement carries the waybill later.');
    }

    public function testNoWaybillFindsNothingWithoutAsking(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::never())->method('create');

        self::assertNull($this->lookup(null, $factory)->findByWaybill(''));
    }

    public function testAWaybillOfNoShipmentFindsNothing(): void
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getItems')->willReturn([]);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        self::assertNull($this->lookup(null, $factory)->findByWaybill('WB-9'));
    }

    private function lookup(?CarrierConfigInterface $config, ?CollectionFactory $factory = null): TrackingLookup
    {
        $configs = $this->createStub(CarrierConfigs::class);
        $configs->method('get')->willReturn($config);

        return new TrackingLookup($factory ?? $this->createStub(CollectionFactory::class), $configs);
    }

    private function shipment(): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);

        return $shipment->setStoreId(2)->setCarrierCode('INPOST_PACZKOMAT');
    }
}
