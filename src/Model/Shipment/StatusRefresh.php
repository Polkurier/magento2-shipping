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
 * Brings a placed shipment up to date with Polkurier, and catches the Magento side up with it:
 * the Magento shipment if it failed before, the waybills Polkurier issued since.
 */
class StatusRefresh
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly Reconciler $reconciler,
        private readonly OrderSnapshot $snapshot,
        private readonly MagentoShipment $magentoShipment,
        private readonly MagentoFollowUp $followUp,
        private readonly DateTime $dateTime,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function refresh(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        $number = $shipment->getPolkurierNumber();
        if ($number === null) {
            throw new LocalizedException(\__('This shipment has no Polkurier order to read.'));
        }
        $remote = $this->reconciler->findByNumber($number);
        $before = $shipment->getWaybills();
        $wasDelivered = $shipment->getDeliveredAt() !== null;
        $wasCancelled = $shipment->getState() === ShipmentInterface::STATE_CANCELLED;
        $this->snapshot->apply($remote, $shipment);
        $shipment->setLastError(null)->setStatusCheckedAt((string)$this->dateTime->gmtDate());
        $this->shipmentRepository->save($shipment);
        if (!$wasDelivered && $shipment->getDeliveredAt() !== null) {
            $this->followUp->remark($shipment->getShipmentId(), \__(
                'Polkurier order %1 delivered (%2).',
                $number,
                (string)$shipment->getDeliveredAt(),
            ));
        }
        if (!$wasCancelled && $shipment->getState() === ShipmentInterface::STATE_CANCELLED) {
            // Cancelled in the Polkurier panel: its Magento shipment is cleared, as when cancelled here.
            $order = $this->followUp->order($shipment->getOrderId());
            $cleared = $this->followUp->clearShipment($order, $shipment);
            $this->followUp->remark($shipment->getShipmentId(), \__(
                'Polkurier order %1 was cancelled in Polkurier. Its label and tracking numbers were removed; another Polkurier order can be placed on this shipment.',
                $number,
            ));
            $this->followUp->comment($order, $cleared !== null
                ? \__('Polkurier order %1 was cancelled in Polkurier. Magento shipment #%2 stays, without its label and tracking numbers, for another Polkurier order.', $number, $cleared)
                : \__('Polkurier order %1 was cancelled in Polkurier.', $number));

            return $shipment;
        }
        if ($shipment->getShipmentId() === null && $shipment->getState() === ShipmentInterface::STATE_CREATED) {
            // Placed, but the Magento shipment failed back then: try again with every refresh.
            $this->followUp->ship($this->followUp->order($shipment->getOrderId()), $shipment);

            return $shipment;
        }

        if ($shipment->getShipmentId() !== null && \array_diff($shipment->getWaybills(), $before) !== []) {
            $this->magentoShipment->addTracks(
                $this->followUp->order($shipment->getOrderId()),
                $shipment->getShipmentId(),
                $shipment,
                $shipment->getWaybills(),
            );
        }

        return $shipment;
    }

    /**
     * Ships the Magento order with a placed Polkurier order it isn't shipped with yet.
     *
     * @throws LocalizedException
     */
    public function shipOrder(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        if ($shipment->getState() !== ShipmentInterface::STATE_CREATED) {
            throw new LocalizedException(\__('Only a placed Polkurier order can be shipped in Magento.'));
        }
        if ($shipment->getShipmentId() !== null) {
            throw new LocalizedException(\__('The Magento order is already shipped with this Polkurier order.'));
        }
        $error = $this->followUp->ship($this->followUp->order($shipment->getOrderId()), $shipment);
        if ($error !== null) {
            throw new LocalizedException(\__('The Magento shipment could not be created: %1', $error));
        }
        // The error recorded was that the Magento shipment failed; it is resolved now.
        $this->shipmentRepository->save($shipment->setLastError(null));

        return $shipment;
    }
}
