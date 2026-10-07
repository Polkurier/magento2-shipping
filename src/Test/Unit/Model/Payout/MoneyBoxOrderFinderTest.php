<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Payout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Payout\MoneyBoxOrderFinder;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;

#[CoversClass(MoneyBoxOrderFinder::class)]
class MoneyBoxOrderFinderTest extends TestCase
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
    private function moneyBoxOrders(array $inMoneyBox): MoneyBoxOrderFinder
    {
        $items = \array_map(function (string $number): ShipmentInterface {
            $shipment = $this->createStub(ShipmentInterface::class);
            $shipment->method('getPolkurierNumber')->willReturn($number);

            return $shipment;
        }, $inMoneyBox);
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('withCodStatus')->willReturn($items);

        return new MoneyBoxOrderFinder($finder);
    }
}
