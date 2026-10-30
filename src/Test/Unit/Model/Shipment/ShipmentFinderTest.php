<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;

#[CoversClass(ShipmentFinder::class)]
#[AllowMockObjectsWithoutExpectations]
class ShipmentFinderTest extends TestCase
{
    /**
     * What each collection was asked: filters, sort orders and page size, in order.
     *
     * @var list<list<string>>
     */
    private array $queries = [];

    /**
     * @var object[]
     */
    private array $items = [];

    public function testTheOrdersLatestShipmentOfAState(): void
    {
        $this->items = [$this->shipment(9)];

        self::assertSame(9, (int)$this->finder()->latestForOrder(42, [ShipmentInterface::STATE_FAILED])?->getId());
        self::assertSame([[
            'state ' . \json_encode(['in' => ['failed']]),
            'order_id ' . \json_encode(['eq' => 42]),
            'order entity_id DESC',
            'page 1',
        ]], $this->queries);
    }

    public function testNoShipmentIsNull(): void
    {
        self::assertNull($this->finder()->latestForOrder(42));
        self::assertSame(
            [['order_id ' . \json_encode(['eq' => 42]), 'order entity_id DESC', 'page 1']],
            $this->queries,
        );
    }

    public function testTheOrdersShipmentsOldestFirst(): void
    {
        $this->items = [$this->shipment(3), $this->shipment(5)];

        $found = $this->finder()->forOrder(42, ShipmentInterface::LIVE_STATES);

        self::assertCount(2, $found);
        self::assertSame([[
            'state ' . \json_encode(['in' => ShipmentInterface::LIVE_STATES]),
            'order_id ' . \json_encode(['eq' => 42]),
            'order entity_id ASC',
        ]], $this->queries);
    }

    public function testShipmentsLinkedToMagentoShipments(): void
    {
        $finder = $this->finder();
        $finder->linkedTo([11, 12]);
        $finder->latestLinkedTo(11, [ShipmentInterface::STATE_CREATED]);

        self::assertSame([
            ['shipment_id ' . \json_encode(['in' => [11, 12]]), 'order entity_id ASC'],
            [
                'state ' . \json_encode(['in' => ['created']]),
                'shipment_id ' . \json_encode(['eq' => 11]),
                'order entity_id DESC',
                'page 1',
            ],
        ], $this->queries);
    }

    public function testListsOfNothingAskNothing(): void
    {
        $finder = $this->finder();

        self::assertSame([], $finder->linkedTo([]));
        self::assertSame([], $finder->byNumbers([]));
        self::assertSame([], $finder->byIds([]));
        self::assertNull($finder->latestByWaybill(''));
        self::assertSame([], $this->queries);
    }

    public function testShipmentsByNumberIdWaybillAndCashOnDelivery(): void
    {
        $finder = $this->finder();
        $finder->byNumbers(['24680-27'], [ShipmentInterface::STATE_CREATED]);
        $finder->byIds([4, 5]);
        $finder->latestByWaybill('WB-1');
        $finder->withCodStatus('MB');

        self::assertSame([
            [
                'state ' . \json_encode(['in' => ['created']]),
                'polkurier_number ' . \json_encode(['in' => ['24680-27']]),
                'order entity_id ASC',
            ],
            ['entity_id ' . \json_encode(['in' => [4, 5]]), 'order entity_id ASC'],
            ['waybills ' . \json_encode(['finset' => 'WB-1']), 'order entity_id DESC', 'page 1'],
            ['cod_status ' . \json_encode(['eq' => 'MB']), 'order entity_id ASC'],
        ], $this->queries);
    }

    public function testShipmentsDueForAStatusCheckLeastRecentlyCheckedFirst(): void
    {
        $finder = $this->finder();
        $finder->dueForStatusCheck('2026-09-30 09:31:00', 200);
        $finder->dueForStatusCheck(null, 200);

        $due = [
            'state ' . \json_encode(['in' => ['created']]),
            'is_final ' . \json_encode(['eq' => 0]),
        ];
        $ordered = ['order status_checked_at ASC', 'page 200', 'order entity_id ASC'];
        self::assertSame([
            [
                ...$due,
                'status_checked_at ' . \json_encode([['null' => true], ['lt' => '2026-09-30 09:31:00']]),
                ...$ordered,
            ],
            [...$due, ...$ordered],
        ], $this->queries, 'Every store\'s: one account.');
    }

    public function testShipmentsDueForReconciliation(): void
    {
        $this->finder()->dueForReconciliation('2026-09-30 09:55:00', '2026-09-30 09:55:00', 50);

        self::assertSame([[
            'state ' . \json_encode(['in' => ['unknown', 'pending']]),
            'created_at ' . \json_encode(['lt' => '2026-09-30 09:55:00']),
            'status_checked_at ' . \json_encode([['null' => true], ['lt' => '2026-09-30 09:55:00']]),
            'order status_checked_at ASC',
            'page 50',
            'order entity_id ASC',
        ]], $this->queries);
    }

    public function testShipmentsNotPlacedLastTriedBefore(): void
    {
        $this->finder()->failedBefore('2026-09-30 10:00:00');

        self::assertSame([[
            'state ' . \json_encode(['in' => ['failed']]),
            'created_at ' . \json_encode(['lt' => '2026-09-30 10:00:00']),
            'order entity_id ASC',
        ]], $this->queries);
    }

    private function finder(): ShipmentFinder
    {
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(function (): Collection {
            $query = \count($this->queries);
            $this->queries[$query] = [];
            $collection = $this->createStub(Collection::class);
            $collection->method('addFieldToFilter')->willReturnCallback(
                function (string $field, mixed $condition) use ($collection, $query): Collection {
                    $this->queries[$query][] = $field . ' ' . \json_encode($condition);

                    return $collection;
                },
            );
            $collection->method('setOrder')->willReturnCallback(
                function (string $field, string $direction) use ($collection, $query): Collection {
                    $this->queries[$query][] = 'order ' . $field . ' ' . $direction;

                    return $collection;
                },
            );
            $collection->method('setPageSize')->willReturnCallback(
                function (int $size) use ($collection, $query): Collection {
                    $this->queries[$query][] = 'page ' . $size;

                    return $collection;
                },
            );
            $collection->method('getItems')->willReturnCallback(fn(): array => $this->items);

            return $collection;
        });

        return new ShipmentFinder($factory);
    }

    private function shipment(int $id): Shipment
    {
        $shipment = $this->createStub(Shipment::class);
        $shipment->method('getId')->willReturn($id);

        return $shipment;
    }
}
