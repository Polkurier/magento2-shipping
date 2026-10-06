<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum;

/**
 * What identifies a Polkurier order as the shop's, sent as its external_order_id: the number of
 * the Magento shipment it goes with, or of the Magento order.
 */
enum ExternalOrderIdEnum: string
{
    case Shipment = 'shipment';
    case Order = 'order';

    public function label(): string
    {
        return match ($this) {
            self::Shipment => (string)\__('Shipment ID'),
            self::Order => (string)\__('Order ID'),
        };
    }
}
