<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Order\Item;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\Order\Item;
use Polkurier\Shipping\Model\ResourceModel\Order\Item as ItemResource;

class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Item::class, ItemResource::class);
    }

    /**
     * @param int $orderId
     *
     * @return $this
     */
    public function addOrderFilter(int $orderId): self
    {
        $this->addFieldToFilter(Item::FIELD_ORDER_ID, ['eq' => $orderId]);

        return $this;
    }
}
