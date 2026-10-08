<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Details;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment as ShipmentModel;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderApplier;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderMatcher;

/**
 * Gets what the shipment details page shows: the local copy of the Polkurier order and the
 * carrier's scans, read from Polkurier only when they're missing or the scans may have moved on.
 *
 * Opening the page changes nothing but the cached scans. An order copy that's missing is read for
 * the page only; the status refresh, by the cron or the Refresh button, is what stores it and acts
 * on it, for example by shipping the Magento order.
 */
readonly class DetailsLoader
{
    /**
     * How long the scans of a shipment still on its way are trusted before being read again.
     */
    public const int TRACKING_LIFETIME_MINUTES = 60;

    public function __construct(
        private RemoteOrderMatcher $remoteOrderMatcher,
        private RemoteOrderApplier $remoteOrderApplier,
        private ShipmentResource $shipmentResource,
        private ShipmentGateway $shipmentGateway,
        private DateTime $dateTime,
        private Logger $logger,
    ) {}

    /**
     * @return array{shipment: ShipmentInterface, errors: string[]} the shipment as stored now, and
     *         why a missing part could not be read
     */
    public function ensure(ShipmentInterface $shipment): array
    {
        $errors = [];
        if ($shipment->getPolkurierNumber() === null) {
            return ['shipment' => $shipment, 'errors' => $errors];
        }
        if ($shipment->getDetails() === []) {
            try {
                $remote = $this->remoteOrderMatcher->findByNumber((string)$shipment->getPolkurierNumber());
                $shipment = clone $shipment;
                $this->remoteOrderApplier->apply($remote, $shipment);
            } catch (LocalizedException $e) {
                $errors[] = $e->getMessage();
            } catch (\Throwable $e) {
                $this->logger->warning('Polkurier order could not be read for the details page', ['exception' => $e]);
                $errors[] = (string)__('The Polkurier order could not be read.');
            }
        }
        if ($this->isTrackingStale($shipment)) {
            try {
                $this->refreshTracking($shipment);
            } catch (LocalizedException $e) {
                $errors[] = $e->getMessage();
            } catch (\Throwable $e) {
                $this->logger->warning('Polkurier scans could not be read for the details page', ['exception' => $e]);
                $errors[] = (string)__('The carrier\'s scans could not be read.');
            }
        }

        return ['shipment' => $shipment, 'errors' => $errors];
    }

    /**
     * Reads the carrier's scans again and stores them, and only them.
     *
     * @throws LocalizedException
     */
    public function refreshTracking(ShipmentInterface $shipment): void
    {
        $number = $shipment->getPolkurierNumber();
        if ($number === null || $shipment->getState() === ShipmentInterface::STATE_CANCELLED) {
            return;
        }
        try {
            $status = $this->shipmentGateway->getStatus($number, true);
        } catch (LocalizedException $e) {
            throw new LocalizedException(__('The carrier\'s scans could not be read: %1', $e->getMessage()), $e);
        }
        $shipment->setTracking($this->remoteOrderApplier->tracking($status))
            ->setTrackingSyncedAt((string)$this->dateTime->gmtDate());
        /** @var ShipmentModel $shipment */
        $this->shipmentResource->updateColumns((int)$shipment->getId(), [
            ShipmentInterface::FIELD_TRACKING => $shipment->getData(ShipmentInterface::FIELD_TRACKING),
            ShipmentInterface::FIELD_TRACKING_SYNCED_AT => $shipment->getTrackingSyncedAt(),
        ]);
    }

    private function isTrackingStale(ShipmentInterface $shipment): bool
    {
        if ($shipment->getState() !== ShipmentInterface::STATE_CREATED) {
            return false;
        }
        $syncedAt = $shipment->getTrackingSyncedAt();
        if ($syncedAt === null) {
            return true;
        }
        if ($shipment->isFinal()) {
            return false;
        }

        return \strtotime($syncedAt . ' UTC') < $this->dateTime->gmtTimestamp() - self::TRACKING_LIFETIME_MINUTES * 60;
    }
}
