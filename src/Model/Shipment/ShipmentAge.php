<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;

/**
 * How long ago a shipment was recorded, for the rules that wait for Polkurier to catch up.
 */
class ShipmentAge
{
    public function __construct(
        private readonly DateTime $dateTime,
    ) {}

    /**
     * Whether the shipment was recorded more than the given minutes ago; one without a time isn't.
     */
    public function isOlderThan(ShipmentInterface $shipment, int $minutes): bool
    {
        $createdAt = $shipment->getCreatedAt();

        return $createdAt !== null && \strtotime($createdAt . ' UTC') < $this->dateTime->gmtTimestamp() - $minutes * 60;
    }
}
