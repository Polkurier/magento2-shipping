<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Adminhtml;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Plugin\Adminhtml\OrderGridFilterPlugin;

#[CoversClass(OrderGridFilterPlugin::class)]
class OrderGridFilterPluginTest extends TestCase
{
    /** @var array<int, array{string, mixed}> */
    private array $where = [];

    /** @var string[] what the orders' shipping methods are matched with */
    private array $like = [];

    public function testTheCarriersOrdersAreThoseWithOneOfItsMethods(): void
    {
        $this->filter(['eq' => 'polkurier']);

        self::assertSame('main_table.entity_id IN (?)', $this->where[0][0]);
        self::assertSame(['polkurier\\_%'], $this->like, 'Its code and an underscore start the method.');
    }

    public function testWildcardsInACodeAreMatchedAsThemselves(): void
    {
        $this->filter(['eq' => 'my_carrier']);

        self::assertSame(['my\\_carrier\\_%'], $this->like);
    }

    public function testNoCarrierNoFilter(): void
    {
        $collection = $this->collection();
        (new OrderGridFilterPlugin())->aroundAddFieldToFilter($collection, static fn() => self::fail('Not for the grid.'), OrderGridFilterPlugin::FIELD, ['eq' => '']);

        self::assertSame([], $this->where);
    }

    public function testOtherFiltersGoToTheGrid(): void
    {
        $passed = [];
        $collection = $this->collection();

        $result = (new OrderGridFilterPlugin())->aroundAddFieldToFilter(
            $collection,
            static function ($field, $condition) use (&$passed, $collection) {
                $passed = [$field, $condition];

                return $collection;
            },
            'status',
            ['eq' => 'pending'],
        );

        self::assertSame(['status', ['eq' => 'pending']], $passed);
        self::assertSame([], $this->where);
        self::assertSame($collection, $result);
    }

    /**
     * @param array<string, string> $condition
     */
    private function filter(array $condition): void
    {
        $collection = $this->collection();
        $result = (new OrderGridFilterPlugin())->aroundAddFieldToFilter(
            $collection,
            static fn() => self::fail('Not for the grid\'s table, which has no such column.'),
            OrderGridFilterPlugin::FIELD,
            $condition,
        );
        self::assertSame($collection, $result);
        self::assertCount(1, $this->where);
        self::assertInstanceOf(Select::class, $this->where[0][1], 'Matched against the orders found by their method.');
    }

    private function collection(): Collection
    {
        $orders = $this->createStub(Select::class);
        $orders->method('from')->willReturnSelf();
        $orders->method('where')->willReturnCallback(function (string $condition, string $value) use ($orders) {
            $this->like[] = $value;

            return $orders;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($orders);

        $grid = $this->createStub(Select::class);
        $grid->method('where')->willReturnCallback(function (string $condition, mixed $value) use ($grid) {
            $this->where[] = [$condition, $value];

            return $grid;
        });

        $collection = $this->createStub(Collection::class);
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getTable')->willReturnArgument(0);
        $collection->method('getSelect')->willReturn($grid);

        return $collection;
    }
}
