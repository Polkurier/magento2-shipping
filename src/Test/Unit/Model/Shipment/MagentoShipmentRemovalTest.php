<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use Magento\Sales\Model\Order\Shipment\Item as ShipmentItem;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface as PolkurierShipmentRepository;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\MagentoShipmentRemoval;

#[CoversClass(MagentoShipmentRemoval::class)]
#[AllowMockObjectsWithoutExpectations]
class MagentoShipmentRemovalTest extends TestCase
{
    /**
     * @var string[] what happened, in order
     */
    private array $log = [];

    public function testTheShipmentsItemsAreLeftToShipAgain(): void
    {
        $mug = $this->orderItem(5, 3.0);
        $lamp = $this->orderItem(6, 1.0);
        $order = $this->order([5 => $mug, 6 => $lamp], Order::STATE_COMPLETE);
        $cancelled = $this->polkurier(ShipmentInterface::STATE_CANCELLED);

        $number = $this->removal($order, [$cancelled])->remove($order, 56);

        self::assertSame('000000009', $number);
        self::assertSame([1.0, 0.0], [$mug->getQtyShipped(), $lamp->getQtyShipped()], 'Two mugs and the lamp were in it.');
        self::assertNull($cancelled->getShipmentId());
        self::assertSame([Order::STATE_PROCESSING, 'processing'], [$order->getState(), $order->getStatus()]);
        self::assertSame(['begin', 'save polkurier', 'delete 000000009', 'save order', 'commit'], $this->log);
    }

    public function testAShipmentAnotherPolkurierOrderHoldsStays(): void
    {
        $order = $this->order([5 => $this->orderItem(5, 3.0)], Order::STATE_COMPLETE);

        $number = $this->removal($order, [$this->polkurier(ShipmentInterface::STATE_CANCELLED), $this->polkurier(ShipmentInterface::STATE_CREATED)])
            ->remove($order, 56);

        self::assertNull($number);
        self::assertSame([], $this->log);
    }

    public function testAShipmentOfAnotherOrderStays(): void
    {
        $order = $this->order([], Order::STATE_PROCESSING, 43);

        self::assertNull($this->removal($order, [])->remove($order, 56));
        self::assertSame([], $this->log);
    }

    public function testAFailureChangesNothing(): void
    {
        $mug = $this->orderItem(5, 3.0);
        $order = $this->order([5 => $mug], Order::STATE_PROCESSING);
        $removal = $this->removal($order, [], new \RuntimeException('Deadlock'));

        try {
            $removal->remove($order, 56);
            self::fail('The failure is reported.');
        } catch (\RuntimeException) {
        }

        self::assertSame(['begin', 'rollback'], $this->log);
    }

    /**
     * @param Shipment[] $linked the Polkurier orders linked to the Magento shipment
     */
    private function removal(Order $order, array $linked, ?\Exception $deleteFails = null): MagentoShipmentRemoval
    {
        $items = [];
        foreach ([5 => 2.0, 6 => 1.0] as $orderItemId => $qty) {
            $item = $this->createStub(ShipmentItem::class);
            $item->method('getOrderItemId')->willReturn($orderItemId);
            $item->method('getQty')->willReturn($qty);
            $items[] = $item;
        }
        $magentoShipment = $this->createStub(MagentoShipmentModel::class);
        $magentoShipment->method('getOrderId')->willReturn(42);
        $magentoShipment->method('getIncrementId')->willReturn('000000009');
        $magentoShipment->method('getItems')->willReturn($items);
        $shipments = $this->createStub(ShipmentRepositoryInterface::class);
        $shipments->method('get')->willReturn($magentoShipment);
        $shipments->method('delete')->willReturnCallback(function () use ($deleteFails): bool {
            if ($deleteFails !== null) {
                throw $deleteFails;
            }
            $this->log[] = 'delete 000000009';

            return true;
        });
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('save')->willReturnCallback(function (Order $saved): Order {
            $this->log[] = 'save order';

            return $saved;
        });
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getItems')->willReturn($linked);
        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturn($collection);
        $polkurier = $this->createStub(PolkurierShipmentRepository::class);
        $polkurier->method('save')->willReturnCallback(function (ShipmentInterface $shipment): ShipmentInterface {
            $this->log[] = 'save polkurier';

            return $shipment;
        });
        $connection = $this->createStub(AdapterInterface::class);
        foreach (['beginTransaction' => 'begin', 'commit' => 'commit', 'rollBack' => 'rollback'] as $method => $entry) {
            $connection->method($method)->willReturnCallback(function () use ($entry, $connection): AdapterInterface {
                $this->log[] = $entry;

                return $connection;
            });
        }
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);

        return new MagentoShipmentRemoval($shipments, $orders, $collections, $polkurier, $resource);
    }

    /**
     * @param array<int, OrderItem> $items
     */
    private function order(array $items, string $state, int $id = 42): Order
    {
        $config = $this->createStub(OrderConfig::class);
        $config->method('getStateDefaultStatus')->willReturn('processing');
        $order = $this->createPartialMock(Order::class, ['getItemById', 'getConfig', 'getEntityId']);
        $order->method('getItemById')->willReturnCallback(static fn(int $itemId): ?OrderItem => $items[$itemId] ?? null);
        $order->method('getConfig')->willReturn($config);
        $order->method('getEntityId')->willReturn($id);
        $order->setState($state);

        return $order;
    }

    private function orderItem(int $id, float $shipped): OrderItem
    {
        /** @var OrderItem $item */
        $item = (new ObjectManager($this))->getObject(OrderItem::class);
        $item->setId($id);

        return $item->setQtyShipped($shipped);
    }

    private function polkurier(string $state): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);

        return $shipment->setState($state)->setShipmentId(56);
    }
}
