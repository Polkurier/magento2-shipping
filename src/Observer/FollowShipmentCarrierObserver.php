<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment\ActualShipping;
use Psr\Log\LoggerInterface;

/**
 * Brings the order's shipping in line with its Polkurier orders (ActualShipping) whenever one is
 * placed, or stops counting: cancelled, or found not placed after all.
 *
 * Event: polkurier_shipment_save_after. A failure is logged: the Polkurier order stands, and the
 * order keeps the shipping it had.
 */
readonly class FollowShipmentCarrierObserver implements ObserverInterface
{
    public function __construct(
        private ActualShipping $actualShipping,
        private LoggerInterface $logger,
    ) {}

    public function execute(Observer $observer): void
    {
        $shipment = $observer->getEvent()->getData('shipment');
        if (!$shipment instanceof ShipmentInterface || !$shipment instanceof \Magento\Framework\Model\AbstractModel) {
            return;
        }
        $was = $shipment->getOrigData(ShipmentInterface::STATE);
        $is = $shipment->getState();
        if ($was === $is || ($was !== ShipmentInterface::STATE_CREATED && $is !== ShipmentInterface::STATE_CREATED)) {
            return;
        }
        try {
            $this->actualShipping->sync($shipment->getOrderId());
        } catch (\Throwable $e) {
            $this->logger->error('The order\'s shipping was not brought in line with its Polkurier order', [
                'order_id' => $shipment->getOrderId(),
                'polkurier_number' => $shipment->getPolkurierNumber(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
