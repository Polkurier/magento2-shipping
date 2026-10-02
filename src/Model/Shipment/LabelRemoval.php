<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Sales\Api\Data\ShipmentInterface as MagentoShipmentInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;

/**
 * What a cancelled Polkurier order leaves of its Magento shipment: the shipment stays, with its
 * items, but without the order's label, packages and waybills, so another Polkurier order can be
 * placed on it, from the shipment's page ("Create Shipping Label") or the shipment screen.
 */
class LabelRemoval
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly ShipmentTrackRepositoryInterface $trackRepository,
        private readonly CollectionFactory $shipmentCollectionFactory,
    ) {}

    /**
     * @return string|null the number of the Magento shipment cleared; null when it's left as it
     *     is, because another Polkurier order holds it or it isn't the order's
     * @throws \Exception when it can't be cleared
     */
    public function clear(Order $order, ShipmentInterface $cancelled): ?string
    {
        $magentoShipmentId = $cancelled->getShipmentId();
        if ($magentoShipmentId === null) {
            return null;
        }
        $magentoShipment = $this->shipmentRepository->get($magentoShipmentId);
        if ((int)$magentoShipment->getOrderId() !== (int)$order->getEntityId()) {
            return null;
        }
        $linked = $this->shipmentCollectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::SHIPMENT_ID, ['eq' => $magentoShipmentId]);
        foreach ($linked->getItems() as $polkurier) {
            /** @var ShipmentInterface $polkurier */
            if ((int)$polkurier->getId() !== (int)$cancelled->getId()
                && \in_array($polkurier->getState(), MagentoShipment::HOLDING_STATES, true)
            ) {
                return null;
            }
        }

        $waybills = $cancelled->getWaybills();
        foreach ($magentoShipment->getTracks() as $track) {
            if ($track->getCarrierCode() === Polkurier::CODE && \in_array((string)$track->getTrackNumber(), $waybills, true)) {
                $this->trackRepository->delete($track);
            }
        }
        // Magento offers "Create Shipping Label" again once there's none.
        if ($magentoShipment instanceof MagentoShipmentModel) {
            $magentoShipment->setData(MagentoShipmentInterface::SHIPPING_LABEL, null);
            $magentoShipment->setData(MagentoShipmentInterface::PACKAGES, null);
            $this->shipmentRepository->save($magentoShipment);
        }

        return (string)$magentoShipment->getIncrementId();
    }
}
