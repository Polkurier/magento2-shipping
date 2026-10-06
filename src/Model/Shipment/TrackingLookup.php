<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\CarrierConfigs;

/**
 * Finds the Polkurier shipment a waybill belongs to, for its tracking page and carrier.
 */
readonly class TrackingLookup
{
    public function __construct(
        private ShipmentFinder $finder,
        private CarrierConfigs $carrierConfigs,
    ) {}

    /**
     * The label of the carrier the shipment went with, in the store it was ordered in.
     */
    public function getMethodLabel(ShipmentInterface $shipment): string
    {
        $config = $this->carrierConfigs->get($shipment->getCarrierCode());

        return $config !== null ? $config->getLabelForStore((int)$shipment->getStoreId()) : $shipment->getCarrierCode();
    }

    public function findByWaybill(string $waybill): ?ShipmentInterface
    {
        return $this->finder->latestByWaybill($waybill);
    }
}
