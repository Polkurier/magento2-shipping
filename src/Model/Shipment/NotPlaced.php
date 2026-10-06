<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime as DateFormat;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNames;

/**
 * A shipment that wasn't placed, because Polkurier or the connection failed, waits for the
 * merchant only so long: it's placed ("Send again", or the order's next attempt) or discarded. A
 * day after its last attempt the admin is told about it (Model\Admin\AttentionMessage), and a day
 * later still it's removed. None stays "Not placed" for good. Each removal is noted in the order's
 * history.
 */
readonly class NotPlaced
{
    /**
     * From its last attempt to the admin's notice of it.
     */
    public const int NOTICE_AFTER_HOURS = 24;

    /**
     * From its last attempt to its removal.
     */
    public const int LIFETIME_HOURS = 48;

    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
        private ShipmentFinder $finder,
        private MagentoFollowUp $followUp,
        private CarrierNames $carrierNames,
        private DateTime $dateTime,
    ) {}

    /**
     * When it's removed unless placed or discarded first, UTC.
     */
    public function expiresAt(ShipmentInterface $shipment): string
    {
        return $this->expiresAfter((string)$shipment->getCreatedAt());
    }

    /**
     * The same, from when it was last tried, UTC.
     */
    public function expiresAfter(string $lastTried): string
    {
        $from = \strtotime($lastTried . ' UTC') ?: $this->dateTime->gmtTimestamp();

        return \gmdate(DateFormat::DATETIME_PHP_FORMAT, $from + self::LIFETIME_HOURS * 3600);
    }

    /**
     * @throws LocalizedException when it's not a shipment that wasn't placed
     */
    public function discard(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        if ($shipment->getState() !== ShipmentInterface::STATE_FAILED) {
            throw new LocalizedException(\__('Only a shipment that wasn\'t placed can be discarded.'));
        }
        $this->remove($shipment, \__(
            'The %1 shipment that wasn\'t placed was discarded.',
            $this->carrierNames->get($shipment->getCarrierCode()),
        ));

        return $shipment;
    }

    /**
     * Removes those whose last attempt is a day old.
     *
     * @return int how many were removed
     */
    public function expire(): int
    {
        $before = \gmdate(DateFormat::DATETIME_PHP_FORMAT, $this->dateTime->gmtTimestamp() - self::LIFETIME_HOURS * 3600);
        $removed = 0;
        foreach ($this->finder->failedBefore($before) as $shipment) {
            $this->remove($shipment, \__(
                'The %1 shipment that wasn\'t placed was removed, %2 hours after it was last tried. Why it wasn\'t placed: %3',
                $this->carrierNames->get($shipment->getCarrierCode()),
                self::LIFETIME_HOURS,
                \rtrim((string)$shipment->getLastError(), '. '),
            ));
            $removed++;
        }

        return $removed;
    }

    private function remove(ShipmentInterface $shipment, \Magento\Framework\Phrase $comment): void
    {
        $this->shipmentRepository->delete($shipment);
        try {
            $this->followUp->comment($this->followUp->order($shipment->getOrderId()), $comment);
        } catch (LocalizedException) {
            // The order is gone: nothing to note it on.
        }
    }
}
