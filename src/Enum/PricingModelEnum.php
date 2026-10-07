<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum;

/**
 * How the merchant prices a carrier in checkout. The price is the merchant's own, not Polkurier's.
 */
enum PricingModelEnum: string
{
    /**
     * One price, whatever the cart.
     */
    case Fixed = 'FIXED';

    /**
     * Tiers by cart subtotal, e.g. free above a threshold.
     */
    case TierSubtotal = 'TIER_SUBTOTAL';

    /**
     * Tiers by cart weight.
     */
    case TierWeight = 'TIER_WEIGHT';

    /**
     * Tiers by both subtotal and weight.
     */
    case TierSubtotalWeight = 'TIER_SUBTOTAL_WEIGHT';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => (string)__('Fixed price'),
            self::TierSubtotal => (string)__('By cart subtotal'),
            self::TierWeight => (string)__('By cart weight'),
            self::TierSubtotalWeight => (string)__('By cart subtotal and weight'),
        };
    }
}
