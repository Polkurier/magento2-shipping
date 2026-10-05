<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

/**
 * What identifies a Polkurier order as the shop's, sent as its external_order_id: the number of
 * the Magento shipment it goes with, or of the Magento order.
 */
enum ExternalOrderIdEnum: string
{
    case Shipment = 'shipment';
    case Order = 'order';
}
