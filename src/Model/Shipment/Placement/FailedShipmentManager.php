<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Placement;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime as DateFormat;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\Shipment\Details\Formatter;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;

/**
 * A shipment that wasn't placed because Polkurier or the connection failed waits for the merchant
 * only so long, to be placed ("Send again", or the order's next attempt) or discarded. A day after
 * its last attempt the admin is told about it
 * ({@see \Polkurier\Shipping\Model\Shipment\AttentionNotifier}), and a day after that it's removed.
 * None stays "Not placed" for good. Each removal is noted in the order's history.
 */
readonly class FailedShipmentManager
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
        private ShipmentFinder $shipmentFinder,
        private NativeFollowUpService $nativeFollowUpService,
        private OrderLoader $orderLoader,
        private CarrierNameProvider $carrierNameProvider,
        private DateTime $dateTime,
        private Formatter $formatter,
    ) {}

    /**
     * What the pages that place a Polkurier order say about the shipment it would send again:
     * when it was last tried, why it wasn't placed, and when it's removed, in the admin's time.
     *
     * @return array{tried: string, reason: string, removed: string}
     */
    public function describe(ShipmentInterface $shipment): array
    {
        return [
            'tried' => $this->formatter->formatStoredTime($shipment->getCreatedAt()),
            'reason' => \rtrim((string)$shipment->getLastError(), '. '),
            'removed' => $this->formatter->formatStoredTime($this->calculateExpiry((string)$shipment->getCreatedAt())),
        ];
    }

    /**
     * When a shipment that wasn't placed is removed, unless placed or discarded first, counted from
     * its last attempt; UTC.
     */
    public function calculateExpiry(string $lastTried): string
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
            throw new LocalizedException(__('Only a shipment that wasn\'t placed can be discarded.'));
        }
        $this->remove($shipment, __(
            'Unplaced shipment discarded. Carrier: %1.',
            $this->carrierNameProvider->get($shipment->getCarrierCode()),
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
        $before = \gmdate(
            DateFormat::DATETIME_PHP_FORMAT,
            $this->dateTime->gmtTimestamp() - self::LIFETIME_HOURS * 3600,
        );
        $removed = 0;
        foreach ($this->shipmentFinder->findFailedBefore($before) as $shipment) {
            $this->remove($shipment, __(
                'Unplaced shipment removed %2 hours after the last attempt. Carrier: %1. Reason: %3',
                $this->carrierNameProvider->get($shipment->getCarrierCode()),
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
            $this->nativeFollowUpService->addComment($this->orderLoader->get($shipment->getOrderId()), $comment);
        } catch (LocalizedException) {
            // The order is gone: nothing to note it on.
        }
    }
}
