<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\StatusRefresher;
use Psr\Log\NullLogger;

#[CoversClass(StatusRefresher::class)]
#[AllowMockObjectsWithoutExpectations]
class StatusRefresherTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $filters = [];
    private ?string $order = null;

    public function testOnlyUnfinishedShipmentsOfStoresWithStatusUpdatesAreTakenLeastRecentlyCheckedFirst(): void
    {
        $this->refresher([], [1 => true, 2 => false])->refreshPending();

        self::assertSame(ShipmentInterface::STATE_CREATED, $this->filters[ShipmentInterface::STATE]);
        self::assertSame(['eq' => 0], $this->filters[ShipmentInterface::IS_FINAL], 'Finished shipments are excluded by the query.');
        self::assertSame(['in' => [1]], $this->filters[ShipmentInterface::STORE_ID]);
        self::assertSame(['null' => true], $this->filters[ShipmentInterface::STATUS_CHECKED_AT][0]);
        self::assertSame(ShipmentInterface::STATUS_CHECKED_AT, $this->order);
    }

    public function testNothingIsReadWhenNoStoreUpdatesStatuses(): void
    {
        self::assertSame(0, $this->refresher([], [1 => false])->refreshPending());
        self::assertSame([], $this->filters);
    }

    public function testAShipmentWhoseRefreshFailsIsStillMarkedAsChecked(): void
    {
        $failing = $this->shipment(7);
        $working = $this->shipment(8);
        $saved = [];

        $refreshed = $this->refresher([$failing, $working], [1 => true], $saved)->refreshPending();

        self::assertSame(1, $refreshed);
        self::assertNotNull($failing->getStatusCheckedAt(), 'It must not stay first in line.');
        self::assertSame([7], $saved);
    }

    /**
     * @param Shipment[] $shipments
     * @param array<int, bool> $statusSyncByStore
     * @param int[] $saved ids of shipments saved by the refresher itself
     */
    private function refresher(array $shipments, array $statusSyncByStore, array &$saved = []): StatusRefresher
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(function (string $field, mixed $condition) use ($collection): Collection {
            $this->filters[$field] = $condition;

            return $collection;
        });
        $collection->method('setOrder')->willReturnCallback(function (string $field) use ($collection): Collection {
            $this->order = $field;

            return $collection;
        });
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getItems')->willReturn($shipments);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('refreshStatus')->willReturnCallback(function (int $id): ShipmentInterface {
            if ($id === 7) {
                throw new LocalizedException(\__('Polkurier is not answering.'));
            }

            return $this->shipment($id);
        });
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(function (ShipmentInterface $shipment) use (&$saved): ShipmentInterface {
            $saved[] = (int)$shipment->getId();

            return $shipment;
        });

        $stores = [];
        foreach (\array_keys($statusSyncByStore) as $storeId) {
            $store = $this->createStub(StoreInterface::class);
            $store->method('getId')->willReturn($storeId);
            $stores[] = $store;
        }
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);
        $config = $this->createStub(Config::class);
        $config->method('isStatusSyncEnabled')->willReturnCallback(
            static fn(?int $storeId): bool => $statusSyncByStore[$storeId] ?? false,
        );

        return new StatusRefresher($factory, $management, $repository, $storeManager, $config, new NullLogger());
    }

    private function shipment(int $id): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);

        return $shipment->setId($id)->setState(ShipmentInterface::STATE_CREATED);
    }
}
