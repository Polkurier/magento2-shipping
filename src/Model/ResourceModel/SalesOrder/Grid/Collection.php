<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\SalesOrder\Grid;

use Magento\Framework\Data\Collection as DataCollection;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;
use Polkurier\Shipping\Model\ResourceModel\SalesOrder\PolkurierOrders;

/**
 * Magento's orders list, narrowed to the orders shipped with Polkurier. Newest first unless sorted
 * otherwise.
 */
class Collection extends OrderGridCollection
{
    /**
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->where('main_table.entity_id IN (?)', PolkurierOrders::select($this));

        return $this;
    }

    /**
     * A grid sorts by its default column, unless the admin's saved state for it names none, e.g.
     * one saved before the default was set: then by the newest still, not in the table's order.
     *
     * @return $this
     */
    protected function _beforeLoad()
    {
        if ($this->_orders === []) {
            $this->setOrder('main_table.created_at', DataCollection::SORT_ORDER_DESC);
            $this->setOrder('main_table.entity_id', DataCollection::SORT_ORDER_DESC);
        }

        return parent::_beforeLoad();
    }
}
