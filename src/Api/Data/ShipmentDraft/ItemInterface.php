<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data\ShipmentDraft;

/**
 * An item of the Magento order and how many of it the shipment carries.
 *
 * @api
 */
interface ItemInterface
{
    public const string ORDER_ITEM_ID = 'order_item_id';
    public const string QTY = 'qty';

    /**
     * @return int
     */
    public function getOrderItemId(): int;

    /**
     * @param int $orderItemId
     *
     * @return $this
     */
    public function setOrderItemId(int $orderItemId): static;

    /**
     * @return float
     */
    public function getQty(): float;

    /**
     * @param float $qty
     *
     * @return $this
     */
    public function setQty(float $qty): static;
}
