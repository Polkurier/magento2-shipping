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
use Magento\Sales\Api\Data\OrderInterface;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\OrderInterface as PolkurierOrderInterface;
use Polkurier\Shipping\Api\Data\OrderInterfaceFactory;
use Polkurier\Shipping\Api\OrderRepositoryInterface;
use Polkurier\Shipping\Helper\ShipmentHelper;
use Polkurier\Shipping\Model\AddressMapper;

readonly class QuoteSubmitBeforeObserver implements ObserverInterface
{
    /**
     * @param OrderInterfaceFactory $polkurierOrderFactory
     * @param OrderRepositoryInterface $polkurierOrderRepository
     * @param AddressRepositoryInterface $addressRepository
     * @param AddressMapper $addressMapper
     * @param ShipmentHelper $shipmentHelper
     */
    public function __construct(
        /**
         * @var OrderInterfaceFactory
         */
        private OrderInterfaceFactory $polkurierOrderFactory,

        /**
         * @var OrderRepositoryInterface
         */
        private OrderRepositoryInterface $polkurierOrderRepository,

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
        if (!$this->shipmentHelper->isQuotePolkurierShipment($quote)) {
            return;
        }

        $polkurierOrder = $this->initPolkurierOrder($quote);

    }

    /**
     * @param CartInterface $quote
     *
     * @return PolkurierOrderInterface
     */
    private function initPolkurierOrder(CartInterface $quote): PolkurierOrderInterface
    {
        $shipmentData = $quote->getExtensionAttributes()?->getPolkurierShipmentData();
        $order = $this->polkurierOrderFactory->create();
        $order->setCarrierCode($shipmentData->getCarrierCode());
        $recipient = $this->addressMapper->mapQuoteAddress($quote->getShippingAddress());
        $recipient->setPointId($shipmentData->getPointId());
        $order->setRecipient($recipient);
        if ($sender = $this->addressRepository->findOneDefault()) {
            $sender = $this->addressMapper->mapConfigAddress($sender);
            $order->setSender($sender);
        }

        return $order;
    }
}
