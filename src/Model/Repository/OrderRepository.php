<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Repository;

use Magento\Framework\Exception\AlreadyExistsException;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Shipping\Api\Data\OrderInterfaceFactory;
use Polkurier\Shipping\Api\OrderRepositoryInterface;
use Polkurier\Shipping\Model\Order;
use Polkurier\Shipping\Model\ResourceModel\Order as OrderResource;
use Polkurier\Shipping\Model\ResourceModel\Order\CollectionFactory;

readonly class OrderRepository implements OrderRepositoryInterface
{
    /**
     * @param OrderInterfaceFactory $entityFactory
     * @param OrderResource $resource
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private OrderInterfaceFactory $entityFactory,
        private OrderResource $resource,
        private CollectionFactory $collectionFactory,
    ) {}

    /**
     * @return iterable<OrderInterface>
     */
    public function findAll(): iterable
    {
        return $this->collectionFactory->create()->load();
    }

    /**
     * @param int $id
     *
     * @return OrderInterface|null
     */
    public function findOneById(int $id): ?OrderInterface
    {
        $order = $this->entityFactory->create();
        $this->resource->load($order, $id);

        return $order->getId() ? $order : null;
    }

    /**
     * @param int $orderId
     *
     * @return OrderInterface|null
     */
    public function findOneByOrderId(int $orderId): ?OrderInterface
    {
        $order = $this->entityFactory->create();
        $this->resource->load($order, $orderId, Order::FIELD_PARENT_ORDER_ID);

        return $order->getId() ? $order : null;
    }

    /**
     * @param int $shipmentId
     *
     * @return OrderInterface|null
     */
    public function findOneByShipmentId(int $shipmentId): ?OrderInterface
    {
        $order = $this->entityFactory->create();
        $this->resource->load($order, $shipmentId, Order::FIELD_PARENT_SHIPMENT_ID);

        return $order->getId() ? $order : null;
    }

    /**
     * @param OrderInterface $order
     *
     * @return OrderInterface
     * @throws AlreadyExistsException
     */
    public function save(OrderInterface $order): OrderInterface
    {
        $this->resource->save($order);

        return $order;
    }

    /**
     * @param OrderInterface $order
     *
     * @return void
     * @throws \Exception
     */
    public function delete(OrderInterface $order): void
    {
        $this->resource->delete($order);
    }

    /**
     * @param int $id
     *
     * @return void
     * @throws \Exception
     */
    public function deleteById(int $id): void
    {
        $order = $this->findOneById($id);
        if ($order) {
            $this->delete($order);
        }
    }
}
