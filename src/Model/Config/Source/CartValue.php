<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Enum\CartValueEnum;

/**
 * The cart value price tiers by subtotal are compared against.
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
