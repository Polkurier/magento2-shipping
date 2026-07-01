<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data\Order;

use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Webservice\Api\Data\Entity\Order\ItemInterface as BaseItemInterface;

interface ItemInterface extends BaseItemInterface
{
    /**
     * @return int|null
     */
    public function getItemId(): ?int;

    /**
     * @param int|null $itemId
     *
     * @return void
     */
    public function setItemId(?int $itemId): void;

    /**
     * @return int
     */
    public function getOrderId(): int;

    /**
     * @param int $orderId
     *
     * @return void
     */
    public function setOrderId(int $orderId): void;

    /**
     * @return OrderInterface
     */
    public function getOrder(): OrderInterface;

    /**
     * @param OrderInterface $order
     *
     * @return void
     */
    public function setOrder(OrderInterface $order): void;
}
