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

readonly class QuoteSubmitSuccessObserver implements ObserverInterface
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
        /** @var OrderInterface $order */
        $order = $observer->getEvent()->getOrder();
        if (!$this->shipmentHelper->isQuotePolkurierShipment($quote)) {
            return;
        }
        $polkurierOrder = $this->initPolkurierOrder($quote, $order);
        $this->polkurierOrderRepository->save($polkurierOrder);
    }

    /**
     * @param CartInterface $quote
     * @param OrderInterface $order
     *
     * @return PolkurierOrderInterface
     */
    private function initPolkurierOrder(CartInterface $quote, OrderInterface $order): PolkurierOrderInterface
    {
        $shipmentData = $quote->getExtensionAttributes()?->getPolkurierShipmentData();
        $polkurierOrder = $this->polkurierOrderFactory->create();
        $polkurierOrder->setParentOrderId((int)$order->getId());
        $polkurierOrder->setCarrierCode($shipmentData->getCarrierCode());
        $recipient = $this->addressMapper->mapQuoteAddress($quote->getShippingAddress());
        $recipient->setPointId($shipmentData->getPointId());
        $polkurierOrder->setRecipient($recipient);
        if ($sender = $this->addressRepository->findOneDefault()) {
            $sender = $this->addressMapper->mapConfigAddress($sender);
            $polkurierOrder->setSender($sender);
        }

        return $polkurierOrder;
    }
}
