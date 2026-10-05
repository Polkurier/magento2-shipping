<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;

/**
 * Shipment types.
 */
readonly class ShipmentType extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            ShipmentTypeEnum::Box->value => (string)\__('Parcel'),
            ShipmentTypeEnum::Envelope->value => (string)\__('Envelope'),
            ShipmentTypeEnum::Palette->value => (string)\__('Pallet'),
        ];
    }
}
