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
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;

/**
 * Links the Polkurier order placed for a shipping label to the Magento shipment it was placed for,
 * once Magento has saved that shipment. The link is saved in the same transaction. A cancelled
 * Polkurier order the shipment had is replaced: it stays linked, and its waybills are removed.
 */
readonly class LinkLabelledShipmentObserver implements ObserverInterface
{
    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
        private NativeShipmentService $nativeShipmentService,
        private NativeFollowUpService $followUp,
    ) {}

    public function execute(Observer $observer): void
    {
        $magentoShipment = $observer->getEvent()->getData('shipment');
        if (!$magentoShipment instanceof Shipment || !$magentoShipment->getData(NativeLabelService::FIELD_LINK)) {
            return;
        }
        $shipment = $this->shipmentRepository->get((int)$magentoShipment->getData(NativeLabelService::FIELD_LINK));
        $magentoShipment->unsetData(NativeLabelService::FIELD_LINK);
        $this->nativeShipmentService->release((int)$magentoShipment->getId());
        $this->shipmentRepository->save($shipment->setShipmentId((int)$magentoShipment->getId()));
        $this->followUp->remarkLinked($shipment);
    }
}
