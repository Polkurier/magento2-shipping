<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Shipment\MagentoShipment;
use Polkurier\Shipping\Model\Shipment\OwnOrders;
use Polkurier\Shipping\Model\Shipment\SendAgain;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;

#[CoversClass(SendAgain::class)]
#[AllowMockObjectsWithoutExpectations]
class SendAgainTest extends TestCase
{
    private const int ORDER_ID = 9;

    public function testARefusedShipmentOfAnOrderWithItemsLeftCanBeSentAgain(): void
    {
        self::assertTrue($this->sendAgain(latest: 14, canShip: true)->isPossibleFor(14, self::ORDER_ID, ShipmentInterface::STATE_FAILED));
    }

    public function testSoCanOneWhoseOrderHasAMagentoShipmentWithoutAPolkurierOrder(): void
    {
        self::assertTrue($this->sendAgain(latest: 14, canShip: false, free: [9])->isPossibleFor(14, self::ORDER_ID, ShipmentInterface::STATE_FAILED));
    }

    public function testNotWhenTheOrderHasNothingToSendItWith(): void
    {
        self::assertFalse($this->sendAgain(latest: 14, canShip: false)->isPossibleFor(14, self::ORDER_ID, ShipmentInterface::STATE_FAILED));
    }

    public function testNotWhenSomethingWasTriedSince(): void
    {
        self::assertFalse($this->sendAgain(latest: 15, canShip: true)->isPossibleFor(14, self::ORDER_ID, ShipmentInterface::STATE_FAILED));
    }

    public function testOnlyAShipmentThatWasntPlaced(): void
    {
        foreach ([ShipmentInterface::STATE_CREATED, ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_PENDING, ShipmentInterface::STATE_CANCELLED] as $state) {
            self::assertFalse($this->sendAgain(latest: 14, canShip: true)->isPossibleFor(14, self::ORDER_ID, $state), $state);
        }
    }

    public function testNotForAnOrderTheModuleDoesntShip(): void
    {
        $orders = $this->createStub(OwnOrders::class);
        $orders->method('get')->willThrowException(new LocalizedException(\__('Not a Polkurier order.')));

        self::assertFalse($this->sendAgain(latest: 14, canShip: true, orders: $orders)->isPossibleFor(14, self::ORDER_ID, ShipmentInterface::STATE_FAILED));
    }

    /**
     * @param int[] $free ids of the order's Magento shipments without a Polkurier order
     */
    private function sendAgain(int $latest, bool $canShip, array $free = [], ?OwnOrders $orders = null): SendAgain
    {
        $latestShipment = $this->createStub(ShipmentInterface::class);
        $latestShipment->method('getId')->willReturn($latest);
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('latestForOrder')->willReturn($latestShipment);

        $order = $this->createStub(Order::class);
        $order->method('canShip')->willReturn($canShip);
        if ($orders === null) {
            $orders = $this->createStub(OwnOrders::class);
            $orders->method('get')->willReturn($order);
        }
        $magentoShipment = $this->createStub(MagentoShipment::class);
        $magentoShipment->method('getFreeShipments')->willReturn(\array_fill_keys($free, ['increment_id' => '000000009', 'replaces' => null]));

        return new SendAgain(
            $this->createStub(ShipmentRepositoryInterface::class),
            $finder,
            $orders,
            $magentoShipment,
            $this->createStub(UrlInterface::class),
        );
    }
}
