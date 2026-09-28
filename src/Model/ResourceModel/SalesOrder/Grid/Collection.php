<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\SalesOrder\Grid;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;
use Polkurier\Shipping\Model\ResourceModel\SalesOrder\PolkurierOrders;

/**
 * Magento's orders list, narrowed to the orders shipped with Polkurier.
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
}
