<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum\Config;

/**
 * What is sent as a Polkurier order's external_order_id to identify it as the shop's: the number of
 * the Magento shipment it goes with, or of the Magento order.
 */
enum ExternalOrderIdEnum: string
{
    case Shipment = 'shipment';
    case Order = 'order';

    public function label(): string
    {
        return match ($this) {
            self::Shipment => (string)__('Shipment ID'),
            self::Order => (string)__('Order ID'),
        };
    }
}
