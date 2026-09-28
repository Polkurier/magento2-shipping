<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Sales\Model\Order;
use Magento\Sales\Api\Data\ShipmentTrackCreationInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentTrackInterfaceFactory;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config;

/**
 * Ships the Magento order that a Polkurier order was placed for, and keeps its tracking numbers
 * equal to the Polkurier waybills.
 */
class MagentoShipment
{
    public function __construct(
        private readonly ShipOrderInterface $shipOrder,
        private readonly ShipmentTrackCreationInterfaceFactory $trackCreationFactory,
        private readonly ShipmentTrackInterfaceFactory $trackFactory,
        private readonly ShipmentTrackRepositoryInterface $trackRepository,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly Config $config,
    ) {}

    /**
     * Ships whatever is left to ship on the order, or, when everything is shipped already, attaches
     * the waybills to its latest shipment.
     *
     * @return int|null the Magento shipment id
     */
    public function ship(Order $order, ShipmentInterface $shipment): ?int
    {
        if ($order->canShip()) {
            $tracks = [];
            foreach ($shipment->getWaybills() as $waybill) {
                $tracks[] = $this->trackCreationFactory->create()
                    ->setTrackNumber($waybill)
                    ->setTitle($this->getTrackTitle($order, $shipment->getCarrierCode()))
                    ->setCarrierCode(Polkurier::CODE);
            }

            return (int)$this->shipOrder->execute(
                (int)$order->getEntityId(),
                [],
                $this->config->isNotifyCustomer((int)$order->getStoreId()),
                false,
                null,
                $tracks,
            );
        }

        $shipmentId = $this->getLatestShipmentId($order);
        if ($shipmentId !== null) {
            $this->addTracks($order, $shipmentId, $shipment, $shipment->getWaybills());
        }

        return $shipmentId;
    }

    /**
     * Adds tracks for waybills the Magento shipment does not carry yet.
     *
     * @param string[] $waybills
     */
    public function addTracks(Order $order, int $shipmentId, ShipmentInterface $shipment, array $waybills): void
    {
        $magentoShipment = $this->shipmentRepository->get($shipmentId);
        $known = [];
        foreach ($magentoShipment->getTracks() as $track) {
            $known[] = (string)$track->getTrackNumber();
        }
        foreach (\array_diff($waybills, $known) as $waybill) {
            $track = $this->trackFactory->create()
                ->setParentId($shipmentId)
                ->setOrderId((int)$order->getEntityId())
                ->setTrackNumber($waybill)
                ->setTitle($this->getTrackTitle($order, $shipment->getCarrierCode()))
                ->setCarrierCode(Polkurier::CODE);
            $this->trackRepository->save($track);
        }
    }

    private function getLatestShipmentId(Order $order): ?int
    {
        $latest = null;
        foreach ($order->getShipmentsCollection() as $magentoShipment) {
            $latest = \max((int)$latest, (int)$magentoShipment->getId());
        }

        return $latest ?: null;
    }

    /**
     * The title customers see for a track: the shipping method they chose.
     */
    public function getTrackTitle(Order $order, string $carrierCode): string
    {
        $description = (string)$order->getShippingDescription();

        return $description !== '' ? $description : $carrierCode;
    }
}
