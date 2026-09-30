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
 * The orders placed with one of the Polkurier methods, the only ones the module ships. The
 * module's index on sales_order.shipping_method keeps the prefix match from reading every order.
 */
class PolkurierOrders
{
    public static function select(AbstractCollection $collection): Select
    {
        return $collection->getConnection()->select()
            ->from($collection->getTable('sales_order'), ['entity_id'])
            ->where('shipping_method LIKE ?', Polkurier::CODE . '\\_%');
    }
}
