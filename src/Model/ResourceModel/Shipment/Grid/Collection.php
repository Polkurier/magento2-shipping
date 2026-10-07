<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Shipment\Grid;

use Magento\Framework\Data\Collection as DataCollection;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Polkurier\Shipping\Api\Data\ShipmentInterface;

/**
 * Polkurier shipments for the admin grids, with the order number they belong to. Newest first
 * unless sorted otherwise.
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
        $this->addFilterToMap(ShipmentInterface::FIELD_ENTITY_ID, 'main_table.entity_id');
        $this->addFilterToMap(ShipmentInterface::FIELD_ORDER_ID, 'main_table.order_id');
        $this->addFilterToMap(ShipmentInterface::FIELD_SHIPMENT_ID, 'main_table.shipment_id');
        $this->addFilterToMap(ShipmentInterface::FIELD_CREATED_AT, 'main_table.created_at');
        $this->addFilterToMap(ShipmentInterface::FIELD_STATE, 'main_table.state');
        $this->addFilterToMap(ShipmentInterface::FIELD_STORE_ID, 'main_table.store_id');
        $this->addFilterToMap('increment_id', 'sales_order.increment_id');

        return $this;
    }

    /**
     * A grid sorts by its default column, unless the admin's saved state for it names none, e.g.
     * one saved before the default was set: then by the newest still, not in the table's order.
     */
    protected function _beforeLoad(): static
    {
        if ($this->_orders === []) {
            $this->setOrder('main_table.created_at', DataCollection::SORT_ORDER_DESC);
            $this->setOrder('main_table.entity_id', DataCollection::SORT_ORDER_DESC);
        }

        return parent::_beforeLoad();
    }
}
