<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum;

/**
 * What packing automatically does with the pack templates' weight, the most their parcels may weigh.
 */
enum WeightLimitEnum: string
{
    /**
     * Packs by size alone; a parcel over its template's limit is pointed out.
     */
    case Warn = 'warn';

    /**
     * Splits parcels, or takes a bigger template, to stay under the limit where it can; what can't
     * be kept under it is packed by size and pointed out.
     */
    case KeepUnder = 'keep_under';

    public function label(): string
    {
        return match ($this) {
            self::Warn => (string)\__('Only warn about heavier parcels'),
            self::KeepUnder => (string)\__('Keep parcels under them where possible'),
        };
    }
}
