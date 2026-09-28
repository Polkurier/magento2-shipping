<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\SalesOrder;

use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\Carrier\Polkurier;

/**
 * The orders shipped with Polkurier: placed with one of its methods, or given a Polkurier shipment
 * afterwards.
 */
class PolkurierOrders
{
    public static function select(AbstractCollection $collection): Select
    {
        $connection = $collection->getConnection();
        $byMethod = $connection->select()
            ->from($collection->getTable('sales_order'), ['entity_id'])
            ->where('shipping_method LIKE ?', Polkurier::CODE . '\\_%');
        $byShipment = $connection->select()
            ->from($collection->getTable('polkurier_shipment'), ['order_id']);

        $select = $connection->select();
        $select->union([$byMethod, $byShipment], Select::SQL_UNION);

        return $select;
    }
}
