<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Sales\Api\Data\ShipmentInterface as MagentoShipmentInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;

/**
 * The Polkurier tracking numbers on a Magento shipment.
 */
final class PolkurierTracks
{
    /**
     * Removes those of the given waybills; the shipment's other tracks stay.
     *
     * @param string[] $waybills
     */
    public static function remove(ShipmentTrackRepositoryInterface $tracks, MagentoShipmentInterface $shipment, array $waybills): void
    {
        foreach ($shipment->getTracks() as $track) {
            if ($track->getCarrierCode() === Polkurier::CODE && \in_array((string)$track->getTrackNumber(), $waybills, true)) {
                $tracks->delete($track);
            }
        }
    }
}
