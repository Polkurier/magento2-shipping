<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
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
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;

/**
 * Keeps what the shipment details page shows up to date: the local copy of the Polkurier order and
 * the carrier's scans. The page shows what is stored at once, and has them read from Polkurier in
 * the background each time it opens, unless nothing about the order changes any more.
 *
 * The copy is read and stored by a status refresh, which also acts on what it reads, for example by
 * shipping the Magento order.
 */
readonly class DetailsLoader
{
    public function __construct(
        private StatusRefreshService $statusRefreshService,
        private RemoteOrderApplier $remoteOrderApplier,
        private ShipmentResource $shipmentResource,
        private ShipmentGateway $shipmentGateway,
        private DateTime $dateTime,
        private Logger $logger,
    ) {}

    /**
     * Whether refresh() has anything to read: the shipment has a Polkurier order, and its copy is
     * missing, or its copy or scans may have moved on since.
     */
    public function isRefreshDue(ShipmentInterface $shipment): bool
    {
        return $shipment->getPolkurierNumber() !== null
            && ($this->isCopyStale($shipment) || $this->isTrackingStale($shipment));
    }

    /**
     * Reads from Polkurier and stores what isRefreshDue() finds missing or stale. Either part may
     * fail without stopping the other.
     *
     * @return array{shipment: ShipmentInterface|null, errors: string[]} the shipment as stored after,
     *         or null when nothing could be read; and why a part could not be read
     */
    public function refresh(ShipmentInterface $shipment): array
    {
        if (!$this->isRefreshDue($shipment)) {
            return ['shipment' => $shipment, 'errors' => []];
        }
        $errors = [];
        $read = false;
        if ($this->isCopyStale($shipment)) {
            try {
                $shipment = $this->statusRefreshService->refresh((int)$shipment->getId());
                $read = true;
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
                $read = true;
            } catch (LocalizedException $e) {
                $errors[] = $e->getMessage();
            } catch (\Throwable $e) {
                $this->logger->warning('Polkurier scans could not be read for the details page', ['exception' => $e]);
                $errors[] = (string)__('The carrier\'s scans could not be read.');
            }
        }

        return ['shipment' => $read ? $shipment : null, 'errors' => $errors];
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
        $shipment->setTracking($this->remoteOrderApplier->mapTracking($status))
            ->setTrackingSyncedAt((string)$this->dateTime->gmtDate());
        /** @var ShipmentModel $shipment */
        $this->shipmentResource->updateColumns((int)$shipment->getId(), [
            ShipmentInterface::FIELD_TRACKING => $shipment->getData(ShipmentInterface::FIELD_TRACKING),
            ShipmentInterface::FIELD_TRACKING_SYNCED_AT => $shipment->getTrackingSyncedAt(),
        ]);
    }

    private function isCopyStale(ShipmentInterface $shipment): bool
    {
        return $shipment->getDetails() === []
            || $shipment->getState() === ShipmentInterface::STATE_CREATED && !$shipment->isFinal();
    }

    private function isTrackingStale(ShipmentInterface $shipment): bool
    {
        return $shipment->getState() === ShipmentInterface::STATE_CREATED
            && ($shipment->getTrackingSyncedAt() === null || !$shipment->isFinal());
    }
}
