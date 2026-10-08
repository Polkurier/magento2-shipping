<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Enum\Config\ParcelWeightEnum;

/**
 * What a parcel of a pack template weighs.
 */
readonly class ParcelWeight extends AbstractOptions
{
    public function toArray(): array
    {
        $options = [];
        foreach (ParcelWeightEnum::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
