<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\Order\PackTypeEnum;

/**
 * Parcel types.
 */
class PackType extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            PackTypeEnum::Standard->value => (string)\__('Standard'),
            PackTypeEnum::NonStandard->value => (string)\__('Non-standard'),
            PackTypeEnum::Long->value => (string)\__('Long'),
            PackTypeEnum::HalfPalette->value => (string)\__('Half pallet'),
            PackTypeEnum::Palette->value => (string)\__('Pallet'),
        ];
    }
}
