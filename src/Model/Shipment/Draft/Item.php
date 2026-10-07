<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Draft;

use Magento\Framework\DataObject;
use Polkurier\Shipping\Api\Data\ShipmentDraft\ItemInterface;

/**
 * An item of the Magento order and how many of it the shipment carries.
 */
class Item extends DataObject implements ItemInterface
{
    public function getOrderItemId(): int
    {
        return (int)$this->getData(self::FIELD_ORDER_ITEM_ID);
    }

    public function setOrderItemId(int $orderItemId): static
    {
        return $this->setData(self::FIELD_ORDER_ITEM_ID, $orderItemId);
    }

    public function getQty(): float
    {
        return (float)$this->getData(self::FIELD_QTY);
    }

    public function setQty(float $qty): static
    {
        return $this->setData(self::FIELD_QTY, $qty);
    }
}
