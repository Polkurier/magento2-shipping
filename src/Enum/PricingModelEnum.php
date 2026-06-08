<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum;

enum PricingModelEnum: string
{
    case FIXED = 'FIXED';
    case TIER_SUBTOTAL = 'TIER_SUBTOTAL';
    case TIER_WEIGHT = 'TIER_WEIGHT';
    case TIER_SUBTOTAL_WEIGHT = 'TIER_SUBTOTAL_WEIGHT';
}
