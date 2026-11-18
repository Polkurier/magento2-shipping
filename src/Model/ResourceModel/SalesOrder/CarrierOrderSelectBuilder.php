<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\SalesOrder;

use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * The orders placed with one of a carrier's methods: their shipping method is the carrier's code,
 * an underscore and the method's. The module's index on sales_order.shipping_method keeps the
 * prefix match from reading every order.
 */
final class CarrierOrderSelectBuilder
{
    public static function buildSelect(AbstractCollection $collection, string $carrierCode): Select
    {
        $prefix = \addcslashes($carrierCode, '\\%_') . '\\_%';

        return $collection->getConnection()->select()
            ->from($collection->getTable('sales_order'), ['entity_id'])
            ->where('shipping_method LIKE ?', $prefix);
    }
}
