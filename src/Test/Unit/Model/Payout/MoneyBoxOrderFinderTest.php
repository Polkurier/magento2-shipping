<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
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
        $orders = $this->moneyBoxOrders(['24680-10', '24680-12']);

        self::assertSame(['24680-10', '24680-12'], $orders->numbers());
        self::assertSame(['24680-99', '24680-19'], $orders->unknown(['24680-10', '24680-99', '24680-19']));
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
