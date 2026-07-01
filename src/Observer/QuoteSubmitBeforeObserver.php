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
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\OrderInterfaceFactory;
use Polkurier\Shipping\Helper\ShipmentHelper;
use Polkurier\Shipping\Model\AddressMapper;

readonly class QuoteSubmitBeforeObserver implements ObserverInterface
{
    /**
     * @param OrderExtensionFactory $orderExtensionFactory
     * @param OrderInterfaceFactory $polkurierOrderFactory
     * @param AddressMapper $addressMapper
     * @param ShipmentHelper $shipmentHelper
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

        /**
         * @var AddressRepositoryInterface
         */
        private AddressRepositoryInterface $addressRepository,

        /**
         * @var AddressMapper
         */
        private AddressMapper $addressMapper,

        /**
         * @var ShipmentHelper
         */
        private ShipmentHelper $shipmentHelper,
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
        if ($this->shipmentHelper->isQuotePolkurierShipment($quote)
            && $shipmentData = $quote->getExtensionAttributes()?->getPolkurierShipmentData()
        ) {
            $orderExtension = $order->getExtensionAttributes() ?: $this->orderExtensionFactory->create();
            $orderData = $this->polkurierOrderFactory->create();
            $orderData->setCarrierCode($shipmentData->getCarrierCode());
            $recipient = $this->addressMapper->mapQuoteAddress($quote->getShippingAddress());
            if ($sender = $this->addressRepository->findOneDefault()) {
                $sender = $this->addressMapper->mapConfigAddress($sender);
                $orderData->setSender($sender);
            }
            $recipient->setPointId($shipmentData->getPointId());
            $orderData->setRecipient($recipient);
            $orderExtension->setPolkurierShipmentData($orderData);
            $order->setExtensionAttributes($orderExtension);
        }
    }
}
