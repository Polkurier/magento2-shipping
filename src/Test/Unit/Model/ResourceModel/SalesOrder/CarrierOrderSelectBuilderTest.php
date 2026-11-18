<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\ResourceModel\SalesOrder;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\ResourceModel\SalesOrder\CarrierOrderSelectBuilder;

#[CoversClass(CarrierOrderSelectBuilder::class)]
#[AllowMockObjectsWithoutExpectations]
class CarrierOrderSelectBuilderTest extends TestCase
{
    public function testOnlyTheCarriersOwnMethodsMatch(): void
    {
        self::assertSame(['shipping_method LIKE ?', 'polkurier\_%'], $this->buildCondition('polkurier'));
    }

    public function testACodeIsMatchedAsItIsNotAsAPattern(): void
    {
        self::assertSame(['shipping_method LIKE ?', 'my\_carrier\%\\\\\_%'], $this->buildCondition('my_carrier%\\'));
    }

    /**
     * @return array{0: string, 1: mixed} the condition and its value
     */
    private function buildCondition(string $carrierCode): array
    {
        $where = [];
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(function (string $condition, mixed $value) use (&$where, $select) {
            $where = [$condition, $value];

            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $collection = $this->createStub(AbstractCollection::class);
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getTable')->willReturnArgument(0);

        CarrierOrderSelectBuilder::buildSelect($collection, $carrierCode);

        return $where;
    }
}
