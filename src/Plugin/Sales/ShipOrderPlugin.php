<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Sales;

use Magento\Sales\Api\Data\ShipmentCommentCreationInterface;
use Magento\Sales\Api\Data\ShipmentCreationArgumentsInterface;
use Magento\Sales\Api\Data\ShipmentTrackCreationInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Shipment\Status\TrackAdoptionService;

/**
 * Links the Polkurier orders of the tracking numbers a Magento shipment is created with, e.g. over
 * REST, once it's saved ({@see TrackAdoptionService}). The module's own tracks are skipped.
 */
readonly class ShipOrderPlugin
{
    public function __construct(
        private TrackAdoptionService $trackAdoptionService,
    ) {}

    /**
     * @param int|string $result the Magento shipment's id
     * @param int $orderId
     * @param array<mixed> $items
     * @param bool $notify
     * @param bool $appendComment
     * @param ShipmentTrackCreationInterface[] $tracks
     * @param array<mixed> $packages
     *
     * @return int|string
     */
    public function afterExecute(
        ShipOrderInterface $subject,
        int|string $result,
        $orderId,
        array $items = [],
        $notify = false,
        $appendComment = false,
        ?ShipmentCommentCreationInterface $comment = null,
        array $tracks = [],
        array $packages = [],
        ?ShipmentCreationArgumentsInterface $arguments = null,
    ): int|string {
        $numbers = [];
        foreach ($tracks as $track) {
            if ($track->getCarrierCode() !== Polkurier::CODE) {
                $numbers[] = (string)$track->getTrackNumber();
            }
        }
        if ($numbers !== []) {
            $this->trackAdoptionService->adopt((int)$result, $numbers);
        }

        return $result;
    }
}
