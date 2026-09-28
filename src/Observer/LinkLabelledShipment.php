<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Shipment;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Shipment\NativeLabel;

/**
 * Links the Polkurier order placed for a shipping label to the Magento shipment it was placed for,
 * once Magento has saved that shipment. The link is saved in the same transaction.
 */
class LinkLabelledShipment implements ObserverInterface
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
    ) {}

    public function execute(Observer $observer): void
    {
        $magentoShipment = $observer->getEvent()->getData('shipment');
        if (!$magentoShipment instanceof Shipment || !$magentoShipment->getData(NativeLabel::LINK)) {
            return;
        }
        $shipment = $this->shipmentRepository->get((int)$magentoShipment->getData(NativeLabel::LINK));
        $magentoShipment->unsetData(NativeLabel::LINK);
        $this->shipmentRepository->save($shipment->setShipmentId((int)$magentoShipment->getId()));
    }
}
