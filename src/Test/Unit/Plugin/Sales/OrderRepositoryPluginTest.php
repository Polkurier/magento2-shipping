<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Sales;

use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderExtensionInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Plugin\Sales\OrderRepositoryPlugin;
use Polkurier\Shipping\Api\Data\ShippingChoiceInterface;
use Polkurier\Shipping\Model\ShippingChoiceStore;
use Polkurier\Shipping\Test\Unit\Support\OrderExtensionWithPoint;

#[CoversClass(OrderRepositoryPlugin::class)]
#[AllowMockObjectsWithoutExpectations]
class OrderRepositoryPluginTest extends TestCase
{
    public function testAListOfOrdersReadsTheirPointsAndChoicesInOneGo(): void
    {
        $point = $this->createStub(OrderPointInterface::class);
        $choice = $this->createStub(ShippingChoiceInterface::class);
        $store = $this->createMock(OrderPointStore::class);
        $store->expects(self::never())->method('getForOrder');
        $store->expects(self::once())->method('getForOrders')->with([7, 8, 9])->willReturn([8 => $point]);
        $choices = $this->createMock(ShippingChoiceStore::class);
        $choices->expects(self::once())->method('getForOrders')->with([7, 8, 9])->willReturn([9 => $choice]);
        $orders = [$this->order(7), $this->order(8), $this->order(9)];
        $result = $this->createStub(OrderSearchResultInterface::class);
        $result->method('getItems')->willReturn($orders);

        $this->plugin($store, $choices)->afterGetList($this->createStub(OrderRepositoryInterface::class), $result);

        self::assertNull($orders[0]->getExtensionAttributes()?->getPolkurierPoint());
        self::assertSame($point, $orders[1]->getExtensionAttributes()?->getPolkurierPoint());
        $extension = $orders[1]->getExtensionAttributes();
        self::assertInstanceOf(OrderExtensionWithPoint::class, $extension);
        self::assertNull($extension->getPolkurierShippingChoice());
        self::assertSame($choice, $orders[2]->getExtensionAttributes()?->getPolkurierShippingChoice());
    }

    public function testAnOrderGetsItsPointAsStoredNow(): void
    {
        $point = $this->createStub(OrderPointInterface::class);
        $store = $this->createStub(OrderPointStore::class);
        $store->method('getForOrders')->willReturn([7 => $point]);
        $order = $this->order(7);

        $this->plugin($store, $this->createStub(ShippingChoiceStore::class))->afterGet($this->createStub(OrderRepositoryInterface::class), $order);

        self::assertSame($point, $order->getExtensionAttributes()?->getPolkurierPoint());
    }

    private function plugin(OrderPointStore $store, ShippingChoiceStore $choices): OrderRepositoryPlugin
    {
        $factory = $this->createStub(OrderExtensionFactory::class);
        $factory->method('create')->willReturnCallback(fn(): OrderExtensionInterface => $this->extension());

        return new OrderRepositoryPlugin($store, $choices, $factory);
    }

    private function order(int $id): OrderInterface
    {
        $extension = null;
        $order = $this->createStub(OrderInterface::class);
        $order->method('getEntityId')->willReturn($id);
        $order->method('getExtensionAttributes')->willReturnCallback(static function () use (&$extension): ?OrderExtensionInterface {
            return $extension;
        });
        $order->method('setExtensionAttributes')->willReturnCallback(static function (OrderExtensionInterface $value) use (&$extension, $order): OrderInterface {
            $extension = $value;

            return $order;
        });

        return $order;
    }

    private function extension(): OrderExtensionInterface
    {
        $point = null;
        $choice = null;
        $extension = $this->createStub(OrderExtensionWithPoint::class);
        $extension->method('getPolkurierPoint')->willReturnCallback(static function () use (&$point): ?OrderPointInterface {
            return $point;
        });
        $extension->method('setPolkurierPoint')->willReturnCallback(static function (OrderPointInterface $value) use (&$point, $extension): OrderExtensionInterface {
            $point = $value;

            return $extension;
        });

        $extension->method('getPolkurierShippingChoice')->willReturnCallback(static function () use (&$choice): ?ShippingChoiceInterface {
            return $choice;
        });
        $extension->method('setPolkurierShippingChoice')->willReturnCallback(static function (ShippingChoiceInterface $value) use (&$choice, $extension): OrderExtensionInterface {
            $choice = $value;

            return $extension;
        });

        return $extension;
    }
}
