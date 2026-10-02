<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;

/**
 * How parcels get to the carrier.
 */
class PickupMode extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            ShipmentDraftInterface::PICKUP_COURIER => (string)\__('A courier collects them'),
            ShipmentDraftInterface::PICKUP_DROP_OFF => (string)\__('I drop them off at a point'),
            ShipmentDraftInterface::PICKUP_MULTI => (string)\__('A courier collects them later, with other shipments'),
        ];
    }
}
