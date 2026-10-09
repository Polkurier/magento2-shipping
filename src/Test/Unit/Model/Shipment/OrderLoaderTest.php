<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\OrderLoader;

#[CoversClass(OrderLoader::class)]
#[AllowMockObjectsWithoutExpectations]
class OrderLoaderTest extends TestCase
{
    public function testAPolkurierOrderIsOursToShip(): void
    {
        $orders = $this->orders('polkurier_DPD');

        self::assertSame('000000042', $orders->getForPolkurier(42)->getIncrementId());
        self::assertSame('000000042', $orders->findForPolkurier(42)?->getIncrementId());
    }

    public function testAnotherCarriersOrderIsNotShippedWithPolkurier(): void
    {
        $orders = $this->orders('flatrate_flatrate');

        self::assertNull($orders->findForPolkurier(42));
        self::assertSame(
            '000000042',
            $orders->get(42)->getIncrementId(),
            'What follows from a shipment goes with any order.',
        );
        $this->expectExceptionMessage(
            'Order #000000042 wasn\'t placed with a Polkurier delivery method, so it isn\'t shipped with Polkurier.',
        );
        $orders->getForPolkurier(42);
    }

    public function testAnOrderThatIsGoneIsNullOrSaidToBeGone(): void
    {
        $orders = $this->orders(null);

        self::assertNull($orders->find(42));
        self::assertNull($orders->findForPolkurier(42));
        self::assertNull($orders->find(0), 'No id, no order.');
        foreach (['get', 'getForPolkurier'] as $method) {
            try {
                $orders->$method(42);
                self::fail($method . ' says the order is gone.');
            } catch (LocalizedException $e) {
                self::assertSame('The order no longer exists.', $e->getMessage());
            }
        }
    }

    private function orders(?string $shippingMethod): OrderLoader
    {
        $repository = $this->createStub(OrderRepositoryInterface::class);
        if ($shippingMethod === null) {
            $repository->method('get')->willThrowException(new NoSuchEntityException());
        } else {
            $order = $this->createStub(Order::class);
            $order->method('getIncrementId')->willReturn('000000042');
            $order->method('getShippingMethod')->willReturn($shippingMethod);
            $repository->method('get')->willReturn($order);
        }

        return new OrderLoader($repository);
    }
}
