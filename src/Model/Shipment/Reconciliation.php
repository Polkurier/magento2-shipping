<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Webservice\Enum\Order\OrderStatusCodeEnum;
use Polkurier\Webservice\Response\Order;

/**
 * Settles attempts whose outcome is unknown: finds the Polkurier order they placed, if any, or
 * links the one the merchant names. What counts as a match is Reconciler's.
 */
readonly class Reconciliation
{
    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
        private Reconciler $reconciler,
        private OrderSnapshot $snapshot,
        private ShipmentAge $age,
        private MagentoFollowUp $followUp,
        private DateTime $dateTime,
        private OrderLock $orderLock,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function reconcile(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        if (!\in_array($shipment->getState(), [ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_PENDING], true)) {
            throw new LocalizedException(\__('Only a shipment whose outcome is unknown can be reconciled.'));
        }
        if ($shipment->getState() === ShipmentInterface::STATE_PENDING && !$this->age->isOlderThan($shipment, ShipmentAge::ANSWER_WAIT_MINUTES)) {
            // Its request may still be waiting for the answer, and would ship it as well.
            throw new LocalizedException(\__('This shipment is still waiting for Polkurier to answer. Try again in a few minutes.'));
        }

        ['sure' => $candidates, 'possible' => $possible] = $this->reconciler->findCandidates($shipment);

        // Settled once: the scheduled reconciliation and the admin's may run at the same time.
        return $this->orderLock->run($shipment->getOrderId(), function () use ($shipmentId, $candidates, $possible): ShipmentInterface {
            $shipment = $this->shipmentRepository->get($shipmentId);
            if (!\in_array($shipment->getState(), [ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_PENDING], true)) {
                return $shipment;
            }

            return $this->settle($shipment, $candidates, $possible);
        });
    }

    /**
     * @param Order[] $candidates orders that match in every respect
     * @param Order[] $possible orders that match but come without their parcels
     */
    private function settle(ShipmentInterface $shipment, array $candidates, array $possible): ShipmentInterface
    {
        $order = $this->followUp->order($shipment->getOrderId());
        $shipment->setStatusCheckedAt((string)$this->dateTime->gmtDate());
        if ($candidates === [] && $possible !== []) {
            // It may well exist: never marked as not placed, and only linked by hand.
            $shipment->setState(ShipmentInterface::STATE_UNKNOWN)->setLastError((string)\__(
                'Polkurier has %1, which may be this order but is listed without its parcels. If it is, link it by hand.',
                \implode(', ', \array_map(static fn($candidate): string => (string)$candidate->getNumber(), $possible)),
            ));
            $this->shipmentRepository->save($shipment);

            return $shipment;
        }
        if ($candidates === []) {
            if ($this->age->isOlderThan($shipment, ShipmentAge::NOT_PLACED_AFTER_MINUTES)) {
                $shipment->setState(ShipmentInterface::STATE_FAILED)
                    ->setLastError((string)\__('Not found in Polkurier: the order was not placed. It is safe to send it again.'));
            } else {
                $shipment->setState(ShipmentInterface::STATE_UNKNOWN)
                    ->setLastError((string)\__('Not found in Polkurier yet. It is checked again automatically.'));
            }
            $this->shipmentRepository->save($shipment);

            return $shipment;
        }

        // Several matches mean the order went through more than once: keep the first one still
        // live, and report the other live ones. Cancelled ones need nothing more.
        \usort($candidates, static fn(Order $a, Order $b): int => [self::isCancelled($a), $a->getDate()] <=> [self::isCancelled($b), $b->getDate()]);
        $adopted = \array_shift($candidates);
        $duplicates = \array_map(
            static fn(Order $candidate): string => (string)$candidate->getNumber(),
            \array_filter($candidates, static fn(Order $candidate): bool => !self::isCancelled($candidate)),
        );
        // Placed, or cancelled since in the Polkurier panel: as the order says.
        $this->snapshot->apply($adopted, $shipment);
        $duplicatesNote = $duplicates === [] ? null : \__(
            'Polkurier also has %1 for this order, probably duplicates. Check them in the Polkurier panel and cancel the ones not needed.',
            \implode(', ', $duplicates),
        );
        $shipment->setLastError($duplicatesNote !== null ? (string)$duplicatesNote : null);
        $this->shipmentRepository->save($shipment);
        if ($shipment->getState() === ShipmentInterface::STATE_CANCELLED) {
            $this->followUp->comment($order, \__(
                'Polkurier order %1 was found after an unconfirmed request, already cancelled in Polkurier. The order can be shipped again.',
                $shipment->getPolkurierNumber(),
            ));

            return $shipment;
        }
        $this->followUp->comment($order, \__('Polkurier order %1 found and linked after an unconfirmed request.', $shipment->getPolkurierNumber()));
        if ($duplicatesNote !== null) {
            $this->followUp->comment($order, $duplicatesNote);
        }
        $this->followUp->ship($order, $shipment);
        $this->followUp->linked($shipment, $order, 'reconcile');

        return $shipment;
    }

    /**
     * Links a shipment whose outcome is unknown to a Polkurier order the merchant identified.
     *
     * @throws LocalizedException
     */
    public function adopt(int $shipmentId, string $polkurierNumber): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        if ($shipment->getState() !== ShipmentInterface::STATE_UNKNOWN) {
            throw new LocalizedException(\__('Only a shipment whose outcome is unknown can be linked by hand.'));
        }
        $remote = $this->reconciler->findByNumber(\trim($polkurierNumber));

        return $this->orderLock->run($shipment->getOrderId(), function () use ($shipmentId, $remote): ShipmentInterface {
            $shipment = $this->shipmentRepository->get($shipmentId);
            if ($shipment->getState() !== ShipmentInterface::STATE_UNKNOWN) {
                throw new LocalizedException(\__('Only a shipment whose outcome is unknown can be linked by hand.'));
            }

            return $this->link($shipment, $remote);
        });
    }

    private function link(ShipmentInterface $shipment, Order $remote): ShipmentInterface
    {
        $this->snapshot->apply($remote, $shipment);
        $shipment->setLastError(null);
        $this->shipmentRepository->save($shipment);
        $order = $this->followUp->order($shipment->getOrderId());
        if ($shipment->getState() === ShipmentInterface::STATE_CANCELLED) {
            $this->followUp->comment($order, \__(
                'Polkurier order %1 linked by hand after an unconfirmed request, already cancelled in Polkurier. The order can be shipped again.',
                $shipment->getPolkurierNumber(),
            ));

            return $shipment;
        }
        $this->followUp->comment($order, \__('Polkurier order %1 linked by hand after an unconfirmed request.', $shipment->getPolkurierNumber()));
        $this->followUp->ship($order, $shipment);
        $this->followUp->linked($shipment, $order, 'adopt');

        return $shipment;
    }

    private static function isCancelled(Order $order): bool
    {
        return $order->getStatusCode() === OrderStatusCodeEnum::Canceled->value;
    }
}
