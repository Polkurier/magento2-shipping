<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\MoneyBox;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\MoneyBox\MoneyBoxOrders;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;

#[CoversClass(MoneyBoxOrders::class)]
class MoneyBoxOrdersTest extends TestCase
{
    public function testNumbersNotKnownAsInTheMoneyBoxAreNamed(): void
    {
        $orders = $this->moneyBoxOrders(['711400-10', '711400-12']);

        self::assertSame(['711400-10', '711400-12'], $orders->numbers());
        self::assertSame(['711400-99', '711400-19'], $orders->unknown(['711400-10', '711400-99', '711400-19']));
        self::assertSame([], $orders->unknown([]), 'Nothing typed in: everything available is paid out.');
    }

    /**
     * @param string[] $inMoneyBox
     */
    private function moneyBoxOrders(array $inMoneyBox): MoneyBoxOrders
    {
        $items = \array_map(function (string $number): ShipmentInterface {
            $shipment = $this->createStub(ShipmentInterface::class);
            $shipment->method('getPolkurierNumber')->willReturn($number);

            return $shipment;
        }, $inMoneyBox);
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getItems')->willReturn($items);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new MoneyBoxOrders($factory);
    }
}
