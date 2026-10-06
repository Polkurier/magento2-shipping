<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Enum\WeightLimitEnum;

/**
 * What packing automatically does with the pack templates' weight limits.
 */
readonly class WeightLimit extends AbstractOptions
{
    public function toArray(): array
    {
        $options = [];
        foreach (WeightLimitEnum::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
