<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
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
use Polkurier\Shipping\Api\Data\ShippingChoiceInterface;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Model\Sdk\ApplicationIdProvider;
use Polkurier\Shipping\Model\ShippingChoice\ShippingChoiceRepository;
use Polkurier\Shipping\Plugin\Sales\OrderRepositoryPlugin;
use Polkurier\Shipping\Test\Unit\Support\OrderExtensionWithPoint;

#[CoversClass(OrderRepositoryPlugin::class)]
#[AllowMockObjectsWithoutExpectations]
class OrderRepositoryPluginTest extends TestCase
{
    public function testAListOfOrdersReadsTheirPointsAndChoicesInOneGo(): void
    {
        $point = $this->createStub(OrderPointInterface::class);
        $choice = $this->createStub(ShippingChoiceInterface::class);
        $store = $this->createMock(OrderPointRepositoryInterface::class);
        $store->expects(self::never())->method('getForOrder');
        $store->expects(self::once())->method('getForOrders')->with([7, 8, 9])->willReturn([8 => $point]);
        $choices = $this->createMock(ShippingChoiceRepository::class);
        $choices->expects(self::once())->method('getForOrders')->with([7, 8, 9])->willReturn([9 => $choice]);
        $orders = [$this->createOrder(7), $this->createOrder(8), $this->createOrder(9)];
        $result = $this->createStub(OrderSearchResultInterface::class);
        $result->method('getItems')->willReturn($orders);

        $this->createPlugin($store, $choices)->afterGetList(
            $this->createStub(OrderRepositoryInterface::class),
            $result,
        );

        self::assertNull($orders[0]->getExtensionAttributes()?->getPolkurierPoint());
        self::assertSame($point, $orders[1]->getExtensionAttributes()?->getPolkurierPoint());
        $extension = $orders[1]->getExtensionAttributes();
        self::assertInstanceOf(OrderExtensionWithPoint::class, $extension);
        self::assertNull($extension->getPolkurierShippingChoice());
        self::assertSame($choice, $orders[2]->getExtensionAttributes()?->getPolkurierShippingChoice());
    }

    public function testEveryOrderCarriesTheInstallationsId(): void
    {
        $orders = [$this->createOrder(7), $this->createOrder(8)];
        $result = $this->createStub(OrderSearchResultInterface::class);
        $result->method('getItems')->willReturn($orders);

        $plugin = $this->createPlugin(
            $this->createStub(OrderPointRepositoryInterface::class),
            $this->createStub(ShippingChoiceRepository::class),
        );
        $plugin->afterGetList($this->createStub(OrderRepositoryInterface::class), $result);

        foreach ($orders as $order) {
            $extension = $order->getExtensionAttributes();
            self::assertInstanceOf(OrderExtensionWithPoint::class, $extension);
            self::assertSame('magento2-0123456789ab', $extension->getPolkurierApplicationId());
        }
    }

    public function testAnOrderGetsItsPointAsStoredNow(): void
    {
        $point = $this->createStub(OrderPointInterface::class);
        $store = $this->createStub(OrderPointRepositoryInterface::class);
        $store->method('getForOrders')->willReturn([7 => $point]);
        $order = $this->createOrder(7);

        $this->createPlugin($store, $this->createStub(ShippingChoiceRepository::class))
            ->afterGet($this->createStub(OrderRepositoryInterface::class), $order);

        self::assertSame($point, $order->getExtensionAttributes()?->getPolkurierPoint());
    }

    private function createPlugin(
        OrderPointRepositoryInterface $store,
        ShippingChoiceRepository $choices,
    ): OrderRepositoryPlugin {
        $factory = $this->createStub(OrderExtensionFactory::class);
        $factory->method('create')->willReturnCallback(fn(): OrderExtensionInterface => $this->createExtension());

        $applicationId = $this->createStub(ApplicationIdProvider::class);
        $applicationId->method('get')->willReturn('magento2-0123456789ab');

        return new OrderRepositoryPlugin($store, $choices, $factory, $applicationId);
    }

    private function createOrder(int $id): OrderInterface
    {
        $extension = null;
        $order = $this->createStub(OrderInterface::class);
        $order->method('getEntityId')->willReturn($id);
        $order->method('getExtensionAttributes')
            ->willReturnCallback(static function () use (&$extension): ?OrderExtensionInterface {
                return $extension;
            });
        $order->method('setExtensionAttributes')->willReturnCallback(
            static function (OrderExtensionInterface $value) use (&$extension, $order): OrderInterface {
                $extension = $value;

                return $order;
            },
        );

        return $order;
    }

    private function createExtension(): OrderExtensionInterface
    {
        $point = null;
        $choice = null;
        $applicationId = null;
        $extension = $this->createStub(OrderExtensionWithPoint::class);
        $extension->method('getPolkurierApplicationId')
            ->willReturnCallback(static function () use (&$applicationId): ?string {
                return $applicationId;
            });
        $extension->method('setPolkurierApplicationId')->willReturnCallback(
            static function (string $value) use (&$applicationId, $extension): OrderExtensionInterface {
                $applicationId = $value;

                return $extension;
            },
        );
        $extension->method('getPolkurierPoint')
            ->willReturnCallback(static function () use (&$point): ?OrderPointInterface {
                return $point;
            });
        $extension->method('setPolkurierPoint')->willReturnCallback(
            static function (OrderPointInterface $value) use (&$point, $extension): OrderExtensionInterface {
                $point = $value;

                return $extension;
            },
        );

        $extension->method('getPolkurierShippingChoice')
            ->willReturnCallback(static function () use (&$choice): ?ShippingChoiceInterface {
                return $choice;
            });
        $extension->method('setPolkurierShippingChoice')->willReturnCallback(
            static function (ShippingChoiceInterface $value) use (&$choice, $extension): OrderExtensionInterface {
                $choice = $value;

                return $extension;
            },
        );

        return $extension;
    }
}
