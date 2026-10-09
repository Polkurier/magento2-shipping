<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;

/**
 * How long ago a shipment was recorded, for the rules that wait for Polkurier to catch up.
 */
readonly class ShipmentAgeChecker
{
    /**
     * A pending attempt younger than this may still be waiting for its answer: no other is sent
     * for the order. Older, it was interrupted, and its outcome is unknown.
     */
    public const int ANSWER_WAIT_MINUTES = 2;

    /**
     * The cron reconciles pending attempts from this age on, with room to spare for a slow answer.
     */
    public const int RECONCILE_PENDING_AFTER_MINUTES = 5;

    /**
     * An attempt Polkurier doesn't list is taken as not placed, rather than not listed yet, from
     * this age on.
     */
    public const int NOT_PLACED_AFTER_MINUTES = 15;

    /**
     * A placed order without its Magento shipment is shipped by a status refresh from this age on.
     * Younger, the request that placed it may still be making it: Magento saves a shipment with a
     * shipping label once the label is back.
     */
    public const int SHIP_AGAIN_AFTER_MINUTES = 5;

    public function __construct(
        private DateTime $dateTime,
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
