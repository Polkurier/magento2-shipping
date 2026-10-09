<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum\Config;

/**
 * The cart value that subtotal price tiers are compared against; either way, after discounts.
 */
enum CartValueEnum: string
{
    /**
     * With tax, as the customer sees the cart.
     */
    case Gross = 'gross';

    /**
     * Without tax.
     */
    case Net = 'net';

    public function label(): string
    {
        return match ($this) {
            self::Gross => (string)__('With tax'),
            self::Net => (string)__('Without tax'),
        };
    }
}
