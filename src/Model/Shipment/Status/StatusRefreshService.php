<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Status;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime as DateFormat;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\Shipment\Details\Formatter;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\OrderLock;
use Polkurier\Shipping\Model\Shipment\ShipmentAgeChecker;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Webservice\Enum\Order\OrderStatusCodeEnum;
use Polkurier\Webservice\Response\Order;

/**
 * Brings placed shipments up to date with Polkurier, and catches the Magento side up with them:
 * the Magento shipment if it failed before, the waybills Polkurier issued since, and the customer's
 * e-mail that waited for them. The order's history notes the waybills issued later, a return and an
 * exception; the Magento shipment's, a delivery.
 *
 * The scheduled run and the admin's "Update statuses" take the batch checked longest ago.
 * Everything that decides whether a shipment is due is in the query, and every shipment taken is
 * marked as checked, even when the check fails: a shipment that is skipped or failing must not
 * stay first in line and crowd out the rest.
 */
readonly class StatusRefreshService
{
    public const int BATCH_SIZE = 200;

    /**
     * Statuses noted on the order when a parcel reaches them: someone usually has to act.
     */
    private const array NOTED_STATUSES = [
        OrderStatusCodeEnum::Return->value,
        OrderStatusCodeEnum::Exception->value,
    ];

    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
        private RemoteOrderMatcher $remoteOrderMatcher,
        private RemoteOrderApplier $remoteOrderApplier,
        private NativeShipmentService $nativeShipmentService,
        private NativeFollowUpService $nativeFollowUpService,
        private OrderLoader $orderLoader,
        private DateTime $dateTime,
        private Formatter $formatter,
        private OrderLock $orderLock,
        private ShipmentAgeChecker $shipmentAgeChecker,
        private ShipmentFinder $shipmentFinder,
        private ShipmentResource $shipmentResource,
        private ConfigProvider $configProvider,
        private Logger $logger,
    ) {}

    /**
     * The scheduled run: the shipments not checked since the previous one. The cron job checks
     * whether the scheduled updates are on.
     */
    public function refreshPending(): void
    {
        // A minute short of the interval, so that a shipment checked by the previous run is due.
        $minAge = \max(1, $this->configProvider->getStatusIntervalMinutes() - 1);

        $this->refreshBatch($minAge);
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

        return $this->update($shipment, $this->remoteOrderMatcher->findByNumber(self::getNumber($shipment)));
    }

    /**
     * Refreshes several shipments as refresh() does each. Their orders are read from Polkurier
     * together where that takes fewer calls:
     * {@see \Polkurier\Shipping\Model\Shipment\Status\RemoteOrderMatcher::findByNumbers()}.
     * A shipment that fails doesn't stop the others.
     *
     * @param int[] $shipmentIds
     *
     * @return array<int, ShipmentInterface|\Throwable> by shipment id, in the order given: the
     *     shipment refreshed, or the error that stopped it, a LocalizedException unless unexpected
     */
    public function refreshMany(array $shipmentIds): array
    {
        $failed = [];
        $shipments = [];
        $numbers = [];
        foreach (\array_unique($shipmentIds) as $shipmentId) {
            try {
                $shipment = $this->shipmentRepository->get($shipmentId);
                $numbers[$shipmentId] = self::getNumber($shipment);
                $shipments[$shipmentId] = $shipment;
            } catch (\Throwable $e) {
                $failed[$shipmentId] = $e;
            }
        }
        $listed = $this->remoteOrderMatcher->findByNumbers($shipments);
        $results = [];
        foreach ($shipmentIds as $shipmentId) {
            if (isset($failed[$shipmentId])) {
                $results[$shipmentId] = $failed[$shipmentId];
                continue;
            }
            $number = $numbers[$shipmentId];
            try {
                $results[$shipmentId] = $this->update(
                    $shipments[$shipmentId],
                    $listed[$number] ?? $this->remoteOrderMatcher->findByNumber($number),
                );
            } catch (\Throwable $e) {
                $results[$shipmentId] = $e;
            }
        }

        return $results;
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
    private static function getNumber(ShipmentInterface $shipment): string
    {
        return $shipment->getPolkurierNumber()
            ?? throw new LocalizedException(__('This shipment has no Polkurier order to read.'));
    }

    /**
     * @throws LocalizedException
     */
    private function update(ShipmentInterface $shipment, Order $remote): ShipmentInterface
    {
        $shipmentId = (int)$shipment->getId();

        // Applied once: a webhook and the scheduled update may refresh it at the same time.
        return $this->orderLock->run($shipment->getOrderId(), fn(): ShipmentInterface => $this->apply(
            $this->shipmentRepository->get($shipmentId),
            $remote,
        ));
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
        $statusBefore = $shipment->getStatusCode();
        $this->remoteOrderApplier->apply($remote, $shipment);
        $shipment->setLastError(null)->setStatusCheckedAt((string)$this->dateTime->gmtDate());
        $this->shipmentRepository->save($shipment);
        if (!$wasDelivered && $shipment->getDeliveredAt() !== null) {
            $this->nativeFollowUpService->addRemark($shipment->getShipmentId(), __(
                'Polkurier order %1 delivered: %2.',
                $number,
                $this->formatter->formatStoredTime($shipment->getDeliveredAt()),
            ));
        }
        if ($shipment->getStatusCode() !== $statusBefore
            && \in_array($shipment->getStatusCode(), self::NOTED_STATUSES, true)
        ) {
            $this->nativeFollowUpService->addComment($this->orderLoader->get($shipment->getOrderId()), __(
                'Polkurier order %1 status: %2.',
                $number,
                $shipment->getStatusLabel() ?: (string)$shipment->getStatusCode(),
            ));
        }
        if (!$wasCancelled && $shipment->getState() === ShipmentInterface::STATE_CANCELLED) {
            // Cancelled in the Polkurier panel: its Magento shipment is cleared, as when cancelled here.
            $this->nativeFollowUpService->followCancellation($shipment, true);

            return $shipment;
        }
        if ($shipment->getShipmentId() === null && $shipment->getState() === ShipmentInterface::STATE_CREATED
            && $shipment->getRequest() !== null
            && $this->shipmentAgeChecker->isOlderThan($shipment, ShipmentAgeChecker::SHIP_AGAIN_AFTER_MINUTES)
        ) {
            // Placed here, but the Magento shipment failed back then: try again with every refresh. One
            // placed elsewhere and linked from its track goes without one when that's taken
            // ({@see TrackAdoptionService}).
            $this->nativeFollowUpService->ship($this->orderLoader->get($shipment->getOrderId()), $shipment);

            return $shipment;
        }

        if ($shipment->getShipmentId() !== null && \array_diff($shipment->getWaybills(), $before) !== []) {
            $order = $this->orderLoader->get($shipment->getOrderId());
            $this->nativeShipmentService->addTracks(
                $order,
                $shipment->getShipmentId(),
                $shipment,
                $shipment->getWaybills(),
            );
            $this->nativeFollowUpService->addComment($order, __(
                'Waybills issued for Polkurier order %1: %2.',
                $number,
                $this->nativeFollowUpService->formatWaybillList($shipment),
            ));
            // The customer's e-mail, when asked for, waits for the waybills.
            $this->nativeFollowUpService->notifyCustomer($order, $shipment);
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
        $error = $this->nativeFollowUpService->ship($this->orderLoader->get($shipment->getOrderId()), $shipment);
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
        $shipmentIds = \array_map(
            static fn(ShipmentInterface $shipment): int => (int)$shipment->getId(),
            $this->shipmentFinder->findDueForStatusCheck($checkedBefore, self::BATCH_SIZE),
        );
        foreach ($this->refreshMany($shipmentIds) as $shipmentId => $result) {
            if (!$result instanceof \Throwable) {
                $refreshed++;
                continue;
            }
            $failed++;
            $this->logger->warning('Polkurier status refresh failed', [
                'shipment' => $shipmentId,
                'exception' => $result,
            ]);
            // Only the check time: the row loaded for the batch is older than what the check may have saved.
            $this->shipmentResource->markChecked($shipmentId, (string)$this->dateTime->gmtDate());
        }

        return ['refreshed' => $refreshed, 'failed' => $failed];
    }
}
