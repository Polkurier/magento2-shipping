<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Status;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime as DateFormat;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\Shipment\Details\Formatter;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\OrderLock;
use Polkurier\Shipping\Model\Shipment\ShipmentAgeChecker;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Webservice\Response\Order;
use Psr\Log\LoggerInterface;

/**
 * Brings placed shipments up to date with Polkurier, and catches the Magento side up with them:
 * the Magento shipment if it failed before, the waybills Polkurier issued since.
 *
 * The scheduled run and the admin's "Update statuses" take the batch checked longest ago.
 * Everything that decides whether a shipment is due is in the query, and every shipment taken is
 * marked as checked, even when the check fails: a shipment that is skipped or failing must not
 * stay first in line and crowd out the rest.
 */
readonly class StatusRefreshService
{
    public const int BATCH_SIZE = 200;

    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
        private RemoteOrderMatcher $remoteOrderMatcher,
        private RemoteOrderApplier $remoteOrderApplier,
        private NativeShipmentService $nativeShipmentService,
        private NativeFollowUpService $followUp,
        private OrderLoader $orderLoader,
        private DateTime $dateTime,
        private Formatter $format,
        private OrderLock $orderLock,
        private ShipmentAgeChecker $shipmentAgeChecker,
        private ShipmentFinder $finder,
        private ShipmentResource $shipmentResource,
        private ConfigProvider $configProvider,
        private LoggerInterface $logger,
    ) {}

    /**
     * The scheduled run: the shipments not checked since the previous one. Whether the scheduled
     * updates are on is the cron job's to check.
     *
     * @return int how many shipments were refreshed
     */
    public function refreshPending(): int
    {
        // A minute short of the interval, so that a shipment checked by the previous run is due.
        $minAge = \max(1, $this->configProvider->getStatusIntervalMinutes() - 1);

        return $this->refreshBatch($minAge)['refreshed'];
    }

    /**
     * Asked for in the admin: the shipments still in motion, however recently checked, oldest
     * check first.
     *
     * @return array{refreshed: int, failed: int}
     */
    public function refreshNow(): array
    {
        return $this->refreshBatch(0);
    }

    /**
     * @throws LocalizedException
     */
    public function refresh(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        $number = $shipment->getPolkurierNumber();
        if ($number === null) {
            throw new LocalizedException(__('This shipment has no Polkurier order to read.'));
        }
        $remote = $this->remoteOrderMatcher->findByNumber($number);

        // Applied once: a webhook and the scheduled update may refresh it at the same time.
        return $this->orderLock->run($shipment->getOrderId(), fn(): ShipmentInterface => $this->apply(
            $this->shipmentRepository->get($shipmentId),
            $remote,
        ));
    }

    /**
     * Ships the Magento order with a placed Polkurier order it isn't shipped with yet.
     *
     * @throws LocalizedException
     */
    public function shipOrder(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);

        return $this->orderLock->run(
            $shipment->getOrderId(),
            fn(): ShipmentInterface => $this->ship($this->shipmentRepository->get($shipmentId)),
        );
    }

    /**
     * @throws LocalizedException
     */
    private function apply(ShipmentInterface $shipment, Order $remote): ShipmentInterface
    {
        $number = (string)$shipment->getPolkurierNumber();
        $before = $shipment->getWaybills();
        $wasDelivered = $shipment->getDeliveredAt() !== null;
        $wasCancelled = $shipment->getState() === ShipmentInterface::STATE_CANCELLED;
        $this->remoteOrderApplier->apply($remote, $shipment);
        $shipment->setLastError(null)->setStatusCheckedAt((string)$this->dateTime->gmtDate());
        $this->shipmentRepository->save($shipment);
        if (!$wasDelivered && $shipment->getDeliveredAt() !== null) {
            $this->followUp->remark($shipment->getShipmentId(), __(
                'Polkurier order %1 delivered (%2).',
                $number,
                $this->format->storedTime($shipment->getDeliveredAt()),
            ));
        }
        if (!$wasCancelled && $shipment->getState() === ShipmentInterface::STATE_CANCELLED) {
            // Cancelled in the Polkurier panel: its Magento shipment is cleared, as when cancelled here.
            $this->followUp->cancelled($shipment, true);

            return $shipment;
        }
        if ($shipment->getShipmentId() === null && $shipment->getState() === ShipmentInterface::STATE_CREATED
            && $this->shipmentAgeChecker->isOlderThan($shipment, ShipmentAgeChecker::SHIP_AGAIN_AFTER_MINUTES)
        ) {
            // Placed, but the Magento shipment failed back then: try again with every refresh.
            $this->followUp->ship($this->orderLoader->get($shipment->getOrderId()), $shipment);

            return $shipment;
        }

        if ($shipment->getShipmentId() !== null && \array_diff($shipment->getWaybills(), $before) !== []) {
            $this->nativeShipmentService->addTracks(
                $this->orderLoader->get($shipment->getOrderId()),
                $shipment->getShipmentId(),
                $shipment,
                $shipment->getWaybills(),
            );
        }

        return $shipment;
    }

    /**
     * @throws LocalizedException
     */
    private function ship(ShipmentInterface $shipment): ShipmentInterface
    {
        if ($shipment->getState() !== ShipmentInterface::STATE_CREATED) {
            throw new LocalizedException(__('Only a placed Polkurier order can be shipped in Magento.'));
        }
        if ($shipment->getShipmentId() !== null) {
            throw new LocalizedException(__('The Magento order is already shipped with this Polkurier order.'));
        }
        $error = $this->followUp->ship($this->orderLoader->get($shipment->getOrderId()), $shipment);
        if ($error !== null) {
            throw new LocalizedException(__('The Magento shipment could not be created: %1', $error));
        }
        // The error recorded was that the Magento shipment failed; it is resolved now.
        $this->shipmentRepository->save($shipment->setLastError(null));

        return $shipment;
    }

    /**
     * @return array{refreshed: int, failed: int}
     */
    private function refreshBatch(int $minAgeMinutes): array
    {
        $checkedBefore = $minAgeMinutes > 0
            ? \gmdate(DateFormat::DATETIME_PHP_FORMAT, $this->dateTime->gmtTimestamp() - $minAgeMinutes * 60)
            : null;
        $refreshed = 0;
        $failed = 0;
        foreach ($this->finder->dueForStatusCheck($checkedBefore, self::BATCH_SIZE) as $shipment) {
            try {
                $this->refresh((int)$shipment->getId());
                $refreshed++;
            } catch (\Throwable $e) {
                $failed++;
                $this->logger->warning('Polkurier status refresh failed', [
                    'shipment' => $shipment->getId(),
                    'exception' => $e,
                ]);
                // Only the check time: the row loaded here is older than what the check may have saved.
                $this->shipmentResource->markChecked((int)$shipment->getId(), (string)$this->dateTime->gmtDate());
            }
        }

        return ['refreshed' => $refreshed, 'failed' => $failed];
    }
}
