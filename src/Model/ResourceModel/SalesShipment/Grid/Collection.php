<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\SalesShipment\Grid;

use Magento\Sales\Model\ResourceModel\Order\Shipment\Grid\Collection as ShipmentGridCollection;
use Polkurier\Shipping\Model\ResourceModel\SalesOrder\PolkurierOrders;

/**
 * Magento's shipments list, narrowed to the shipments of orders shipped with Polkurier.
 */
class Collection extends ShipmentGridCollection
{
    /**
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->where('main_table.order_id IN (?)', PolkurierOrders::select($this));

        return $this;
    }
}
