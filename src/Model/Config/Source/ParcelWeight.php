<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Model\Shipment\ParcelWeightEnum;

/**
 * What a parcel of a pack template weighs.
 */
readonly class ParcelWeight extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            ParcelWeightEnum::Products->value => (string)\__('What the products in it weigh'),
            ParcelWeightEnum::Template->value => (string)\__('The pack template\'s weight'),
        ];
    }
}
