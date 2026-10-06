<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Adminhtml;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;
use Polkurier\Shipping\Model\ResourceModel\SalesOrder\CarrierOrders;

/**
 * The "Shipping carrier" filter of Magento's orders list (sales_order_grid.xml): the orders placed
 * with one of the carrier's methods. The grid's table has the method's description only, so the
 * filter is applied here rather than passed on to it.
 */
readonly class OrderGridFilterPlugin
{
    public const string FIELD = 'shipping_carrier';

    /**
     * @param string|array<mixed> $field
     */
    public function aroundAddFieldToFilter(Collection $subject, callable $proceed, string|array $field, mixed $condition = null): Collection
    {
        if ($field !== self::FIELD) {
            return $proceed($field, $condition);
        }
        $carrierCode = \trim((string)(\is_array($condition) ? \reset($condition) : $condition));
        if ($carrierCode !== '') {
            $subject->getSelect()->where('main_table.entity_id IN (?)', CarrierOrders::select($subject, $carrierCode));
        }

        return $subject;
    }
}
