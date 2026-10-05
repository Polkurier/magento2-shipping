<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Model\Shipment\WeightLimitEnum;

/**
 * What packing automatically does with the pack templates' weight limits.
 */
readonly class WeightLimit extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            WeightLimitEnum::Warn->value => (string)\__('Only warn about heavier parcels'),
            WeightLimitEnum::KeepUnder->value => (string)\__('Keep parcels under them where possible'),
        ];
    }
}
