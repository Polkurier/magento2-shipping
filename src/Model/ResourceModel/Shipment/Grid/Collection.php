<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
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
            'sales_order.entity_id = main_table.' . ShipmentInterface::FIELD_ORDER_ID,
            ['increment_id' => 'sales_order.increment_id'],
        );
        foreach ([
            ShipmentInterface::FIELD_ENTITY_ID,
            ShipmentInterface::FIELD_ORDER_ID,
            ShipmentInterface::FIELD_SHIPMENT_ID,
            ShipmentInterface::FIELD_CREATED_AT,
            ShipmentInterface::FIELD_STATE,
            ShipmentInterface::FIELD_STORE_ID,
        ] as $field) {
            $this->addFilterToMap($field, 'main_table.' . $field);
        }
        $this->addFilterToMap('increment_id', 'sales_order.increment_id');

        return $this;
    }

    /**
     * A grid sorts by its default column. When the admin's saved state names no sort column, the
     * grid sorts newest first, not in the table's order.
     */
    protected function _beforeLoad(): static
    {
        if ($this->_orders === []) {
            $this->setOrder('main_table.' . ShipmentInterface::FIELD_CREATED_AT, DataCollection::SORT_ORDER_DESC);
            $this->setOrder('main_table.' . ShipmentInterface::FIELD_ENTITY_ID, DataCollection::SORT_ORDER_DESC);
        }

        return parent::_beforeLoad();
    }
}
