<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Setup\Patch\Data;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\OrderSnapshot;
use Polkurier\Shipping\Setup\Patch\Data\MarkFinishedShipments;

#[CoversClass(MarkFinishedShipments::class)]
#[AllowMockObjectsWithoutExpectations]
class MarkFinishedShipmentsTest extends TestCase
{
    public function testEveryUnfinishedShipmentIsReadOnceAcrossBatches(): void
    {
        // 1201 shipments, every third one finished: three batches of 500, 500 and 201.
        $ids = \range(1, 1201);
        $read = [];
        $marked = [];

        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () use ($ids, &$read): Collection {
            $after = 0;
            $size = 0;
            $collection = $this->createStub(Collection::class);
            $collection->method('addFieldToFilter')->willReturnCallback(function (string $field, $condition) use (&$after, $collection): Collection {
                if ($field === 'entity_id') {
                    $after = $condition['gt'];
                }

                return $collection;
            });
            $collection->method('setOrder')->willReturnSelf();
            $collection->method('setPageSize')->willReturnCallback(function (int $pageSize) use (&$size, $collection): Collection {
                $size = $pageSize;

                return $collection;
            });
            $collection->method('getItems')->willReturnCallback(function () use ($ids, &$after, &$size, &$read): array {
                $batch = \array_slice(\array_values(\array_filter($ids, static fn(int $id): bool => $id > $after)), 0, $size);
                $read = [...$read, ...$batch];

                return \array_map(function (int $id): Shipment {
                    $shipment = $this->createStub(Shipment::class);
                    $shipment->method('getId')->willReturn($id);

                    return $shipment;
                }, $batch);
            });

            return $collection;
        });
        $snapshot = $this->createStub(OrderSnapshot::class);
        $snapshot->method('isFinal')->willReturnCallback(static fn(Shipment $shipment): bool => $shipment->getId() % 3 === 0);
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('update')->willReturnCallback(function (string $table, array $data, array $where) use (&$marked): int {
            $marked = [...$marked, ...$where['entity_id IN (?)']];

            return \count($where['entity_id IN (?)']);
        });
        $setup = $this->createStub(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnArgument(0);

        (new MarkFinishedShipments($setup, $factory, $snapshot))->apply();

        self::assertSame($ids, $read);
        self::assertCount(400, $marked);
    }
}
