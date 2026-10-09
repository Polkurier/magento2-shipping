<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Screen;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Screen\RetriableShipmentFinder;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;

#[CoversClass(RetriableShipmentFinder::class)]
#[AllowMockObjectsWithoutExpectations]
class RetriableShipmentFinderTest extends TestCase
{
    private const int ORDER_ID = 9;

    public function testARefusedShipmentOfAnOrderWithItemsLeftCanBeSentAgain(): void
    {
        self::assertTrue(
            $this->sendAgain(latest: 14, canShip: true)
                ->isPossibleFor(14, self::ORDER_ID, ShipmentInterface::STATE_FAILED),
        );
    }

    public function testSoCanOneWhoseOrderHasAMagentoShipmentWithoutAPolkurierOrder(): void
    {
        self::assertTrue(
            $this->sendAgain(latest: 14, canShip: false, free: [9])
                ->isPossibleFor(14, self::ORDER_ID, ShipmentInterface::STATE_FAILED),
        );
    }

    public function testNotWhenTheOrderHasNothingToSendItWith(): void
    {
        self::assertFalse(
            $this->sendAgain(latest: 14, canShip: false)
                ->isPossibleFor(14, self::ORDER_ID, ShipmentInterface::STATE_FAILED),
        );
    }

    public function testNotWhenSomethingWasTriedSince(): void
    {
        self::assertFalse(
            $this->sendAgain(latest: 15, canShip: true)
                ->isPossibleFor(14, self::ORDER_ID, ShipmentInterface::STATE_FAILED),
        );
    }

    public function testOnlyAShipmentThatWasntPlaced(): void
    {
        $otherStates = [
            ShipmentInterface::STATE_CREATED,
            ShipmentInterface::STATE_UNKNOWN,
            ShipmentInterface::STATE_PENDING,
            ShipmentInterface::STATE_CANCELLED,
        ];
        foreach ($otherStates as $state) {
            self::assertFalse(
                $this->sendAgain(latest: 14, canShip: true)->isPossibleFor(14, self::ORDER_ID, $state),
                $state,
            );
        }
    }

    public function testNotForAnOrderTheModuleDoesntShip(): void
    {
        $orders = $this->createStub(OrderLoader::class);
        $orders->method('getForPolkurier')->willThrowException(new LocalizedException(__('Not a Polkurier order.')));

        self::assertFalse(
            $this->sendAgain(latest: 14, canShip: true, orders: $orders)
                ->isPossibleFor(14, self::ORDER_ID, ShipmentInterface::STATE_FAILED),
        );
    }

    /**
     * @param int[] $free ids of the order's Magento shipments without a Polkurier order
     */
    private function sendAgain(
        int $latest,
        bool $canShip,
        array $free = [],
        ?OrderLoader $orders = null,
    ): RetriableShipmentFinder {
        $latestShipment = $this->createStub(ShipmentInterface::class);
        $latestShipment->method('getId')->willReturn($latest);
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('latestForOrder')->willReturn($latestShipment);

        $order = $this->createStub(Order::class);
        $order->method('canShip')->willReturn($canShip);
        if ($orders === null) {
            $orders = $this->createStub(OrderLoader::class);
            $orders->method('getForPolkurier')->willReturn($order);
        }
        $magentoShipment = $this->createStub(NativeShipmentService::class);
        $magentoShipment->method('getFreeShipments')->willReturn(
            \array_fill_keys($free, ['increment_id' => '000000009', 'replaces' => null]),
        );

        return new RetriableShipmentFinder(
            $this->createStub(ShipmentRepositoryInterface::class),
            $finder,
            $orders,
            $magentoShipment,
        );
    }
}
