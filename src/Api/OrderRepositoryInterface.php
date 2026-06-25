<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

use Polkurier\Shipping\Api\Data\OrderInterface;

interface OrderRepositoryInterface
{
    /**
     * @return iterable<OrderInterface>
     */
    public function findAll(): iterable;

    /**
     * @param int $id
     *
     * @return OrderInterface|null
     */
    public function findOneById(int $id): ?OrderInterface;

    /**
     * @param int $orderId
     *
     * @return OrderInterface|null
     */
    public function findOneByOrderId(int $orderId): ?OrderInterface;

    /**
     * @param int $shipmentId
     *
     * @return OrderInterface|null
     */
    public function findOneByShipmentId(int $shipmentId): ?OrderInterface;

    /**
     * @param OrderInterface $order
     *
     * @return OrderInterface
     */
    public function save(OrderInterface $order): OrderInterface;

    /**
     * @param OrderInterface $order
     *
     * @return void
     */
    public function delete(OrderInterface $order): void;

    /**
     * @param int $id
     *
     * @return void
     */
    public function deleteById(int $id): void;
}
