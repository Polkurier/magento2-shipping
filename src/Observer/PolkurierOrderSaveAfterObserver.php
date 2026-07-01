<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Shipping\Model\ResourceModel\Order\Address;
use Polkurier\Shipping\Model\ResourceModel\Order\Cod;
use Polkurier\Shipping\Model\ResourceModel\Order\Item;
use Polkurier\Shipping\Model\ResourceModel\Order\Pack;
use Polkurier\Shipping\Model\ResourceModel\Order\Waybill;
use Polkurier\Webservice\Enum\AddressTypeEnum;

readonly class PolkurierOrderSaveAfterObserver implements ObserverInterface
{
    /**
     * @param Address $addressResource
     * @param Cod $codResource
     * @param Item $itemResource
     * @param Pack $packResource
     * @param Waybill $waybillResource
     */
    public function __construct(
        /**
         * @var Address
         */
        private Address $addressResource,

        /**
         * @var Cod
         */
        private Cod $codResource,

        /**
         * @var Item
         */
        private Item $itemResource,

        /**
         * @var Pack
         */
        private Pack $packResource,

        /**
         * @var Waybill
         */
        private Waybill $waybillResource,
    ) {}

    /**
     * @param Observer $observer
     *
     * @return void
     * @throws AlreadyExistsException
     */
    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getEvent()->getPolkurierOrder();
        $this->saveAddresses($order);
        $this->saveCod($order);
        $this->saveItems($order);
        $this->savePacks($order);
        $this->saveWaybills($order);
    }

    /**
     * @param OrderInterface $order
     *
     * @return void
     * @throws AlreadyExistsException
     */
    private function saveAddresses(OrderInterface $order): void
    {
        $senderAddress = $order->getSender();
        $senderAddress->setOrderId($order->getOrderId());
        $senderAddress->setType(AddressTypeEnum::SENDER);
        $this->addressResource->save($senderAddress);

        $recipientAddress = $order->getRecipient();
        $recipientAddress->setOrderId($order->getOrderId());
        $recipientAddress->setType(AddressTypeEnum::RECIPIENT);
        $this->addressResource->save($recipientAddress);
    }

    /**
     * @param OrderInterface $order
     *
     * @return void
     * @throws AlreadyExistsException
     */
    private function saveCod(OrderInterface $order): void
    {
        if (!($cod = $order->getCod())) {
            return;
        }
        $cod->setOrderId($order->getOrderId());
        $this->codResource->save($cod);
    }

    /**
     * @param OrderInterface $order
     *
     * @return void
     * @throws AlreadyExistsException
     */
    private function saveItems(OrderInterface $order): void
    {
        foreach ($order->getItems() as $item) {
            $item->setOrderId($order->getOrderId());
            $this->itemResource->save($item);
        }
    }

    /**
     * @param OrderInterface $order
     *
     * @return void
     * @throws AlreadyExistsException
     */
    private function savePacks(OrderInterface $order): void
    {
        foreach ($order->getPacks() as $pack) {
            $pack->setOrderId($order->getOrderId());
            $this->packResource->save($pack);
        }
    }

    /**
     * @param OrderInterface $order
     *
     * @return void
     * @throws AlreadyExistsException
     */
    private function saveWaybills(OrderInterface $order): void
    {
        foreach ($order->getWaybills() as $waybill) {
            $waybill->setOrderId($order->getOrderId());
            $this->waybillResource->save($waybill);
        }
    }
}
