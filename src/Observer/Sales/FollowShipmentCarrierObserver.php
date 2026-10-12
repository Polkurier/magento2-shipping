<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer\Sales;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Shipment\Native\ActualShippingUpdater;

/**
 * Brings the order's shipping in line with its Polkurier orders ({@see ActualShippingUpdater})
 * whenever one is placed or stops counting (cancelled, or found not placed after all).
 *
 * Event: polkurier_shipment_save_after. A failure is logged: the Polkurier order stands, and the
 * order keeps the shipping it had.
 */
readonly class FollowShipmentCarrierObserver implements ObserverInterface
{
    public function __construct(
        private ActualShippingUpdater $actualShippingUpdater,
        private Logger $logger,
    ) {}

    public function execute(Observer $observer): void
    {
        $shipment = $observer->getEvent()->getData('shipment');
        if (!$shipment instanceof ShipmentInterface || !$shipment instanceof \Magento\Framework\Model\AbstractModel) {
            return;
        }
        $was = $shipment->getOrigData(ShipmentInterface::FIELD_STATE);
        $is = $shipment->getState();
        if ($was === $is || ($was !== ShipmentInterface::STATE_CREATED && $is !== ShipmentInterface::STATE_CREATED)) {
            return;
        }
        try {
            // Placed or found: its own note on the order says what the customer chose.
            $this->actualShippingUpdater->sync($shipment->getOrderId(), $is !== ShipmentInterface::STATE_CREATED);
        } catch (\Throwable $e) {
            $this->logger->error('The order\'s shipping was not brought in line with its Polkurier order', [
                'order_id' => $shipment->getOrderId(),
                'polkurier_number' => $shipment->getPolkurierNumber(),
                'exception' => $e,
            ]);
        }
    }
}
