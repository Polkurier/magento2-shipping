<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Sales\Model\Order;

/**
 * Polkurier takes cash on delivery and insurance in złoty. An order in złoty is used as it is; an
 * order whose base currency is złoty is converted with the rate the order was placed at, which
 * gives its base amounts. Any other order can't be converted: its amounts aren't filled in, and
 * the merchant enters them in złoty.
 */
class PlnAmounts
{
    public const string CURRENCY = 'PLN';

    /**
     * What one unit of the order's currency is worth in złoty, or null when it can't be known.
     */
    public function getRate(Order $order): ?float
    {
        if ($order->getOrderCurrencyCode() === self::CURRENCY) {
            return 1.0;
        }
        $rate = (float)$order->getBaseToOrderRate();
        if ($order->getBaseCurrencyCode() === self::CURRENCY && $rate > 0) {
            return 1 / $rate;
        }

        return null;
    }

    /**
     * An amount in the order's currency, in złoty, or null when it can't be converted.
     */
    public function convert(Order $order, float $amount): ?float
    {
        $rate = $this->getRate($order);

        return $rate === null ? null : \round($amount * $rate, 2);
    }
}
