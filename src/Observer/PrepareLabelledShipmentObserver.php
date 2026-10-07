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
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;

/**
 * Tidies a Magento shipment before it is saved with a label from Magento's packaging popup.
 *
 * Magento titles the tracks it adds for a label with the carrier's configured title, which is not
 * meant for customers; they get the name of the carrier the parcels went with instead, as every
 * other track does, which isn't the order's shipping method when the carrier was changed. A
 * waybill the shipment already tracks is not tracked twice, and a label that was not available is
 * not stored as an empty document.
 */
readonly class PrepareLabelledShipmentObserver implements ObserverInterface
{
    public function __construct(
        private ConfigProvider $configProvider,
        private NativeShipmentService $nativeShipmentService,
    ) {}

    public function execute(Observer $observer): void
    {
        $shipment = $observer->getEvent()->getData('shipment');
        if (!$shipment instanceof Shipment) {
            return;
        }
        if ($shipment->getData(NativeLabelService::FIELD_NO_LABEL)) {
            // Left out of the save, so an existing shipment keeps what it had.
            $shipment->unsetData('shipping_label');
            $shipment->unsetData(NativeLabelService::FIELD_NO_LABEL);
        }

        $carrierCode = (string)$shipment->getData(NativeLabelService::FIELD_CARRIER);
        $shipment->unsetData(NativeLabelService::FIELD_CARRIER);

        $tracks = $shipment->getTracks();
        if (!\is_array($tracks) || $tracks === []) {
            return;
        }
        $magentoTitle = $this->configProvider->getTitle((int)$shipment->getStoreId());
        $order = $shipment->getOrder();
        if ($carrierCode === '') {
            $carrierCode = (string)Polkurier::methodCodeOf((string)$order->getShippingMethod());
        }
        $seen = [];
        $kept = [];
        foreach ($tracks as $track) {
            if ($track->getCarrierCode() !== Polkurier::CODE) {
                $kept[] = $track;
                continue;
            }
            $number = (string)$track->getTrackNumber();
            if (!$track->getEntityId() && isset($seen[$number])) {
                continue;
            }
            $seen[$number] = true;
            if (!$track->getEntityId() && (string)$track->getTitle() === $magentoTitle) {
                $track->setTitle($this->nativeShipmentService->getTrackTitle($order, $carrierCode));
            }
            $kept[] = $track;
        }
        if (\count($kept) !== \count($tracks)) {
            $shipment->setTracks($kept);
        }
    }
}
