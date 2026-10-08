<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Model\Shipment\Placement\ParcelTypeMapper;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;

/**
 * Parcel types: parcels, an envelope and pallets. A shipment's type follows from them
 * ({@see ParcelTypeMapper}).
 */
readonly class PackType extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            PackTypeEnum::Standard->value => (string)__('Standard'),
            PackTypeEnum::NonStandard->value => (string)__('Non-standard'),
            PackTypeEnum::Long->value => (string)__('Long'),
            ParcelTypeMapper::ENVELOPE => (string)__('Envelope'),
            PackTypeEnum::QuarterPalette->value => (string)__('Quarter pallet 60 × 40'),
            PackTypeEnum::HalfPalette->value => (string)__('Half pallet 80 × 60'),
            ParcelTypeMapper::EURO_PALLET => (string)__('EURO pallet 120 × 80'),
            ParcelTypeMapper::EURO_PALLET_NON_STANDARD => (string)__('EURO pallet 120 × 80, non-standard'),
            PackTypeEnum::Palette120x100->value => (string)__('Pallet 120 × 100'),
            PackTypeEnum::Palette->value => (string)__('Pallet 120 × 120'),
            PackTypeEnum::Palette150x150->value => (string)__('Pallet 150 × 150'),
        ];
    }
}
