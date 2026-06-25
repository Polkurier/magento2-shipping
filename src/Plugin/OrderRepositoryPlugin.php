<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin;

use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Polkurier\Shipping\Api\Data\OrderInterfaceFactory;
use Polkurier\Shipping\Api\OrderRepositoryInterface as PolkurierOrderRepositoryInterface;
use Polkurier\Shipping\Helper\ShipmentHelper;

readonly class OrderRepositoryPlugin
{
    /**
     * @param OrderExtensionFactory $extensionFactory
     * @param OrderInterfaceFactory $polkurierOrderFactory
     * @param PolkurierOrderRepositoryInterface $polkurierOrderRepository
     */
    public function __construct(
        /**
         * @var OrderExtensionFactory
         */
        private OrderExtensionFactory $extensionFactory,

        /**
         * @var OrderInterfaceFactory
         */
        private OrderInterfaceFactory $polkurierOrderFactory,

        /**
         * @var PolkurierOrderRepositoryInterface
         */
        private PolkurierOrderRepositoryInterface $polkurierOrderRepository,
    ) {}

    /**
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface $order
     *
     * @return OrderInterface
     */
    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $order): OrderInterface
    {

        return $this->loadExtensionAttributes($order);
    }

    /**
     * @param OrderRepositoryInterface $subject
     * @param OrderSearchResultInterface $result
     *
     * @return OrderSearchResultInterface
     */
    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $result
    ): OrderSearchResultInterface {
        foreach ($result->getItems() as $order) {
            $this->loadExtensionAttributes($order);
        }

        return $result;
    }

    /**
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface $order
     *
     * @return array
     */
    public function beforeSave(OrderRepositoryInterface $subject, OrderInterface $order): array
    {
        if (ShipmentHelper::isOrderPolkurierShipment($order)
            && $polkurierOrder = $order->getExtensionAttributes()?->getPolkurierShipmentData()
        ) {
            $polkurierOrder->setParentOrderId($order->getId());
            $this->polkurierOrderRepository->save($polkurierOrder);
        }

        return [$order];
    }

    /**
     * @param OrderInterface $order
     *
     * @return OrderInterface
     */
    private function loadExtensionAttributes(OrderInterface $order): OrderInterface
    {
        $extensionAttributes = $order->getExtensionAttributes() ?: $this->extensionFactory->create();
        $polkurierOrder = $this->polkurierOrderFactory->create();
        if (ShipmentHelper::isOrderPolkurierShipment($order)) {
            $this->polkurierOrderRepository->findOneByOrderId($order->getId());
        }
        $extensionAttributes->setPolkurierShipmentData($polkurierOrder);
        $order->setExtensionAttributes($extensionAttributes);

        return $order;
    }
}
