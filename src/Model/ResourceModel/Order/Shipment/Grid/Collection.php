<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Order\Shipment\Grid;

use Magento\Sales\Model\ResourceModel\Order\Shipment\Grid\Collection as BaseCollection;
use Polkurier\Shipping\Model\Order;
use Polkurier\Shipping\Model\ResourceModel\Order as OrderResource;

class Collection extends BaseCollection
{
    /**
     * @return $this
     */
    protected function _initSelect(): self
    {
        parent::_initSelect();
        $this->getSelect()->joinInner(
            ['po' => $this->getTable(OrderResource::TABLE_NAME)],
            'main_table.entity_id = po.' . Order::FIELD_PARENT_SHIPMENT_ID,
            ''
        );

        return $this;
    }
}
