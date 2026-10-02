<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Shipment\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Polkurier shipments for the admin grids, with the order number they belong to.
 */
class Collection extends SearchResult
{
    protected function _initSelect(): static
    {
        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['sales_order' => $this->getTable('sales_order')],
            'sales_order.entity_id = main_table.order_id',
            ['increment_id' => 'sales_order.increment_id'],
        );
        $this->addFilterToMap('entity_id', 'main_table.entity_id');
        $this->addFilterToMap('order_id', 'main_table.order_id');
        $this->addFilterToMap('shipment_id', 'main_table.shipment_id');
        $this->addFilterToMap('created_at', 'main_table.created_at');
        $this->addFilterToMap('state', 'main_table.state');
        $this->addFilterToMap('store_id', 'main_table.store_id');
        $this->addFilterToMap('increment_id', 'sales_order.increment_id');

        return $this;
    }
}
