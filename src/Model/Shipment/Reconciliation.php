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

/**
 * Settles attempts whose outcome is unknown: finds the Polkurier order they placed, if any, or
 * links the one the merchant names. What counts as a match is Reconciler's.
 */
class Reconciliation
{
    /**
     * How long after an unconfirmed attempt an order that Polkurier does not list counts as not
     * placed, rather than not listed yet.
     */
    public const int NOT_PLACED_AFTER_MINUTES = 15;

    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly Reconciler $reconciler,
        private readonly OrderSnapshot $snapshot,
        private readonly ShipmentAge $age,
        private readonly MagentoFollowUp $followUp,
        private readonly DateTime $dateTime,
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

        ['sure' => $candidates, 'possible' => $possible] = $this->reconciler->findCandidates($shipment);

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
            if ($this->age->isOlderThan($shipment, self::NOT_PLACED_AFTER_MINUTES)) {
                $shipment->setState(ShipmentInterface::STATE_FAILED)
                    ->setLastError((string)\__('Not found in Polkurier: the order was not placed. It is safe to send it again.'));
            } else {
                $shipment->setState(ShipmentInterface::STATE_UNKNOWN)
                    ->setLastError((string)\__('Not found in Polkurier yet. It is checked again automatically.'));
            }
            $this->shipmentRepository->save($shipment);

            return $shipment;
        }

        // Several matches mean the order went through more than once: keep the first, report the rest.
        \usort($candidates, static fn($a, $b): int => $a->getDate() <=> $b->getDate());
        $adopted = \array_shift($candidates);
        $duplicates = \array_map(static fn($candidate): string => (string)$candidate->getNumber(), $candidates);
        $this->snapshot->apply($adopted, $shipment);
        $duplicatesNote = $duplicates === [] ? null : \__(
            'Polkurier also has %1 for this order, probably duplicates. Check them in the Polkurier panel and cancel the ones not needed.',
            \implode(', ', $duplicates),
        );
        $shipment->setState(ShipmentInterface::STATE_CREATED)->setLastError($duplicatesNote !== null ? (string)$duplicatesNote : null);
        $this->shipmentRepository->save($shipment);
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
        $remote = $this->reconciler->findByNumber($shipment, \trim($polkurierNumber));
        $this->snapshot->apply($remote, $shipment);
        $shipment->setState(ShipmentInterface::STATE_CREATED)->setLastError(null);
        $this->shipmentRepository->save($shipment);
        $order = $this->followUp->order($shipment->getOrderId());
        $this->followUp->comment($order, \__('Polkurier order %1 linked by hand after an unconfirmed request.', $shipment->getPolkurierNumber()));
        $this->followUp->ship($order, $shipment);
        $this->followUp->linked($shipment, $order, 'adopt');

        return $shipment;
    }
}
