<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Enum\Config\CartValueEnum;

/**
 * The cart value that subtotal price tiers are compared against.
 */
readonly class CartValue extends AbstractOptions
{
    public function toArray(): array
    {
        $options = [];
        foreach (CartValueEnum::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
