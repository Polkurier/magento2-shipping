<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Sales;

use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Shipment\Status\TrackAdoptionService;

/**
 * Links the Polkurier order of a tracking number added to a Magento shipment, e.g. over REST, once
 * it's saved ({@see TrackAdoptionService}). The module's own tracks are skipped.
 */
readonly class ShipmentTrackRepositoryPlugin
{
    public function __construct(
        private TrackAdoptionService $trackAdoptionService,
    ) {}

    public function afterSave(
        ShipmentTrackRepositoryInterface $subject,
        ShipmentTrackInterface $result,
    ): ShipmentTrackInterface {
        if ($result->getCarrierCode() !== Polkurier::CODE && $result->getParentId()) {
            $this->trackAdoptionService->adopt((int)$result->getParentId(), [(string)$result->getTrackNumber()]);
        }

        return $result;
    }
}
