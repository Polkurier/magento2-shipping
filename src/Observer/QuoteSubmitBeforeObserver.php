<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Polkurier\Shipping\Api\Data\OrderInterfaceFactory;
use Polkurier\Shipping\Helper\ShipmentHelper;

readonly class QuoteSubmitBeforeObserver implements ObserverInterface
{
    /**
     * @param OrderExtensionFactory $orderExtensionFactory
     * @param OrderInterfaceFactory $polkurierOrderFactory
     */
    public function __construct(
        /**
         * @var OrderExtensionFactory
         */
        private OrderExtensionFactory $orderExtensionFactory,

        /**
         * @var OrderInterfaceFactory
         */
        private OrderInterfaceFactory $polkurierOrderFactory,
    ) {}

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var CartInterface $quote */
        $quote = $observer->getEvent()->getQuote();
        /** @var OrderInterface $order */
        $order = $observer->getEvent()->getOrder();
        if (ShipmentHelper::isQuotePolkurierShipment($quote)
            && $shipmentData = $quote?->getExtensionAttributes()->getPolkurierShipmentData()
        ) {
            $orderExtension = $order->getExtensionAttributes() ?: $this->orderExtensionFactory->create();
            $orderData = $this->polkurierOrderFactory->create();
            $orderData->setCarrierCode($shipmentData->getCarrierCode());
            $orderData->getRecipient()->setPointId($shipmentData->getPointId());
            $orderExtension->setPolkurierShipmentData($orderData);
            $order->setExtensionAttributes($orderExtension);
        }
    }
}
