<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Api\Data\ShipmentInterface;

/**
 * States of a Polkurier shipment in Magento.
 */
class ShipmentState extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            ShipmentInterface::STATE_PENDING => (string)\__('Sending'),
            ShipmentInterface::STATE_CREATED => (string)\__('Placed'),
            ShipmentInterface::STATE_UNKNOWN => (string)\__('Outcome unknown'),
            ShipmentInterface::STATE_FAILED => (string)\__('Not placed'),
            ShipmentInterface::STATE_CANCELLED => (string)\__('Cancelled'),
        ];
    }
}
