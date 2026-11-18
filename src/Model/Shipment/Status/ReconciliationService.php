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
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\OrderLock;
use Polkurier\Shipping\Model\Shipment\ShipmentAgeChecker;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Webservice\Entity\Order;
use Polkurier\Webservice\Enum\Order\OrderStatusCodeEnum;

/**
 * Settles attempts whose outcome is unknown: finds the Polkurier order they placed, if any, or
 * links the one the merchant names, if they placed it. What counts as a match is
 * {@see \Polkurier\Shipping\Model\Shipment\Status\RemoteOrderMatcher}'s.
 *
 * It runs on its own, never at the merchant's request: before another attempt for the order is
 * placed (settleForOrder()), and on schedule (reconcilePending()).
 *
 * The scheduled run takes shipments whose outcome is unknown, and pending ones
 * old enough that their request must have been interrupted, least recently checked first, like
 * the status refresh: found orders are adopted, and orders still missing after a while are marked
 * as not placed.
 */
readonly class ReconciliationService
{
    public const int BATCH_SIZE = 50;
    public const int RECHECK_AFTER_MINUTES = 5;

    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
        private RemoteOrderMatcher $remoteOrderMatcher,
        private RemoteOrderApplier $remoteOrderApplier,
        private ShipmentAgeChecker $shipmentAgeChecker,
        private NativeFollowUpService $nativeFollowUpService,
        private OrderLoader $orderLoader,
        private DateTime $dateTime,
        private OrderLock $orderLock,
        private ShipmentFinder $shipmentFinder,
        private ShipmentResource $shipmentResource,
        private Logger $logger,
    ) {}

    /**
     * The scheduled run, without anyone having to ask.
     */
    public function reconcilePending(): void
    {
        $now = $this->dateTime->gmtTimestamp();
        $shipments = $this->shipmentFinder->findDueForReconciliation(
            \gmdate(DateFormat::DATETIME_PHP_FORMAT, $now - ShipmentAgeChecker::RECONCILE_PENDING_AFTER_MINUTES * 60),
            \gmdate(DateFormat::DATETIME_PHP_FORMAT, $now - self::RECHECK_AFTER_MINUTES * 60),
            self::BATCH_SIZE,
        );
        foreach ($shipments as $shipment) {
            try {
                $this->reconcile((int)$shipment->getId());
            } catch (\Throwable $e) {
                $this->logger->warning('Polkurier reconciliation failed', [
                    'shipment' => $shipment->getId(),
                    'exception' => $e,
                ]);
                // Only the check time: the row loaded here is older than what the check may have saved.
                $this->shipmentResource->markChecked((int)$shipment->getId(), (string)$this->dateTime->gmtDate());
            }
        }
    }

    /**
     * Settles the order's earlier attempts whose outcome is unknown, before another one is placed:
     * one found placed, cancelled or not placed meanwhile no longer holds the order back. One that
     * can't be settled yet stays as it is, with its reason.
     */
    public function settleForOrder(int $orderId): void
    {
        $unsettled = $this->shipmentFinder->findForOrder($orderId, ShipmentInterface::UNSETTLED_STATES);
        foreach ($unsettled as $shipment) {
            try {
                $this->reconcile((int)$shipment->getId());
            } catch (\Throwable $e) {
                $this->logger->warning('Polkurier reconciliation before placing failed', [
                    'shipment' => $shipment->getId(),
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * @throws LocalizedException
     */
    public function reconcile(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        if (!\in_array($shipment->getState(), ShipmentInterface::UNSETTLED_STATES, true)) {
            throw new LocalizedException(__('Only a shipment whose outcome is unknown can be reconciled.'));
        }
        if ($shipment->getState() === ShipmentInterface::STATE_PENDING
            && !$this->shipmentAgeChecker->isOlderThan($shipment, ShipmentAgeChecker::ANSWER_WAIT_MINUTES)
        ) {
            // Its request may still be waiting for the answer, and would ship it as well.
            throw new LocalizedException(__(
                'This shipment is still waiting for Polkurier to answer. Try again in a few minutes.',
            ));
        }

        ['sure' => $candidates, 'possible' => $possible] = $this->remoteOrderMatcher->findCandidates($shipment);

        // Settled once: the scheduled reconciliation and the admin's may run at the same time.
        return $this->orderLock->run($shipment->getOrderId(), function () use (
            $shipmentId,
            $candidates,
            $possible,
        ): ShipmentInterface {
            $shipment = $this->shipmentRepository->get($shipmentId);
            if (!\in_array($shipment->getState(), ShipmentInterface::UNSETTLED_STATES, true)) {
                return $shipment;
            }

            return $this->settle($shipment, $candidates, $possible);
        });
    }

    /**
     * Links a shipment whose outcome is unknown to a Polkurier order the merchant identified. Only
     * an order this installation placed for the shipment, and no other shipment holds, is linked: one
     * placed in the Polkurier panel, or by another shop on the account, is refused.
     *
     * @throws LocalizedException
     */
    public function adopt(int $shipmentId, string $polkurierNumber): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        if ($shipment->getState() !== ShipmentInterface::STATE_UNKNOWN) {
            throw new LocalizedException(__('Only a shipment whose outcome is unknown can be linked by hand.'));
        }
        if ($shipment->getExternalOrderId() === null) {
            throw new LocalizedException(__(
                'This shipment was sent without this shop\'s IDs, '
                . 'so no Polkurier order can be linked to it by hand.',
            ));
        }
        $remote = $this->remoteOrderMatcher->findByNumber(\trim($polkurierNumber));
        if (!$this->remoteOrderMatcher->isPlacedFor($shipment, $remote)) {
            throw new LocalizedException(__(
                'Polkurier order %1 was not placed by this shop for this shipment, '
                . 'so it can\'t be linked to it.',
                $remote->getNumber(),
            ));
        }

        return $this->orderLock->run($shipment->getOrderId(), function () use (
            $shipmentId,
            $remote,
        ): ShipmentInterface {
            $shipment = $this->shipmentRepository->get($shipmentId);
            if ($shipment->getState() !== ShipmentInterface::STATE_UNKNOWN) {
                throw new LocalizedException(__('Only a shipment whose outcome is unknown can be linked by hand.'));
            }
            // Sibling shipments of one Magento order may carry the same ids.
            if ($this->remoteOrderMatcher->isHeldByAnother($shipment, $remote)) {
                throw new LocalizedException(__(
                    'Polkurier order %1 is already linked to another shipment.',
                    $remote->getNumber(),
                ));
            }

            return $this->link($shipment, $remote);
        });
    }

    /**
     * @param Order[] $candidates orders that match in every respect
     * @param Order[] $possible orders that match but come without their parcels
     */
    private function settle(ShipmentInterface $shipment, array $candidates, array $possible): ShipmentInterface
    {
        $order = $this->orderLoader->get($shipment->getOrderId());
        $shipment->setStatusCheckedAt((string)$this->dateTime->gmtDate());
        if ($candidates === [] && $possible !== []) {
            // It may well exist: never marked as not placed, and only linked by hand.
            $shipment->setState(ShipmentInterface::STATE_UNKNOWN)->setLastError((string)__(
                'Polkurier has %1, which may be this order but is listed without its parcels. '
                . 'If it is, link it by hand.',
                \implode(', ', \array_map(
                    static fn($candidate): string => (string)$candidate->getNumber(),
                    $possible,
                )),
            ));
            $this->shipmentRepository->save($shipment);

            return $shipment;
        }
        if ($candidates === []) {
            if ($this->shipmentAgeChecker->isOlderThan($shipment, ShipmentAgeChecker::NOT_PLACED_AFTER_MINUTES)) {
                $shipment->setState(ShipmentInterface::STATE_FAILED)
                    ->setLastError((string)__(
                        'Not found in Polkurier: the order was not placed. It is safe to send it again.',
                    ));
            } else {
                $shipment->setState(ShipmentInterface::STATE_UNKNOWN)
                    ->setLastError((string)__('Not found in Polkurier yet. It is checked again automatically.'));
            }
            $this->shipmentRepository->save($shipment);

            return $shipment;
        }

        // Several matches mean the order went through more than once: keep the first one still
        // live, and report the other live ones. Cancelled ones need nothing more.
        \usort(
            $candidates,
            static fn(Order $a, Order $b): int => [self::isCancelled($a), $a->getDate()]
                <=> [self::isCancelled($b), $b->getDate()],
        );
        $adopted = \array_shift($candidates);
        $duplicates = \array_map(
            static fn(Order $candidate): string => (string)$candidate->getNumber(),
            \array_filter($candidates, static fn(Order $candidate): bool => !self::isCancelled($candidate)),
        );
        // Placed, or cancelled since in the Polkurier panel: as the order says.
        $this->remoteOrderApplier->apply($adopted, $shipment);
        $duplicatesNote = $duplicates === [] ? null : __(
            'Possible duplicate Polkurier orders: %1. Verify them in the Polkurier panel.',
            \implode(', ', $duplicates),
        );
        $shipment->setLastError($duplicatesNote !== null ? (string)$duplicatesNote : null);
        $this->shipmentRepository->save($shipment);
        if ($shipment->getState() === ShipmentInterface::STATE_CANCELLED) {
            $this->nativeFollowUpService->addComment($order, __(
                'Polkurier order %1 cancelled in the Polkurier panel.',
                $shipment->getPolkurierNumber(),
            ));

            return $shipment;
        }
        $this->nativeFollowUpService->notePlaced($order, $shipment);
        if ($duplicatesNote !== null) {
            $this->nativeFollowUpService->addComment($order, $duplicatesNote);
        }
        $this->nativeFollowUpService->ship($order, $shipment);
        $this->nativeFollowUpService->followLink($shipment, $order, 'reconcile');

        return $shipment;
    }

    private function link(ShipmentInterface $shipment, Order $remote): ShipmentInterface
    {
        $this->remoteOrderApplier->apply($remote, $shipment);
        $shipment->setLastError(null);
        $this->shipmentRepository->save($shipment);
        $order = $this->orderLoader->get($shipment->getOrderId());
        if ($shipment->getState() === ShipmentInterface::STATE_CANCELLED) {
            $this->nativeFollowUpService->addComment($order, __(
                'Linked with Polkurier order %1, cancelled in the Polkurier panel.',
                $shipment->getPolkurierNumber(),
            ));

            return $shipment;
        }
        $this->nativeFollowUpService->notePlaced($order, $shipment, true);
        $this->nativeFollowUpService->ship($order, $shipment);
        $this->nativeFollowUpService->followLink($shipment, $order, 'adopt');

        return $shipment;
    }

    private static function isCancelled(Order $order): bool
    {
        return $order->getStatusCode() === OrderStatusCodeEnum::Canceled;
    }
}
