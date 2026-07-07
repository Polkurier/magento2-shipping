<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Api;

use Magento\Sales\Api\Data\ShipmentExtensionFactory;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\Data\ShipmentSearchResultInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Api\Data\OrderInterfaceFactory;
use Polkurier\Shipping\Api\OrderRepositoryInterface;
use Polkurier\Shipping\Helper\ShipmentHelper;

readonly class ShipmentRepositoryPlugin
{
    /**
     * @param ShipmentExtensionFactory $extensionFactory
     * @param OrderInterfaceFactory $orderFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param ShipmentHelper $shipmentHelper
     */
    public function __construct(
        /**
         * @var ShipmentExtensionFactory
         */
        private ShipmentExtensionFactory $extensionFactory,

        /**
         * @var OrderInterfaceFactory
         */
        private OrderInterfaceFactory $orderFactory,

        /**
         * @var OrderRepositoryInterface
         */
        private OrderRepositoryInterface $orderRepository,

        /**
         * @var ShipmentHelper
         */
        private ShipmentHelper $shipmentHelper,
    ) {}

    /**
     * @param ShipmentRepositoryInterface $subject
     * @param ShipmentInterface $order
     *
     * @return ShipmentInterface
     */
    public function afterGet(ShipmentRepositoryInterface $subject, ShipmentInterface $order): ShipmentInterface
    {
        return $this->loadExtensionAttributes($order);
    }

    /**
     * @param ShipmentRepositoryInterface $subject
     * @param ShipmentSearchResultInterface $result
     *
     * @return ShipmentSearchResultInterface
     */
    public function afterGetList(
        ShipmentRepositoryInterface $subject,
        ShipmentSearchResultInterface $result
    ): ShipmentSearchResultInterface {
        foreach ($result->getItems() as $shipment) {
            $this->loadExtensionAttributes($shipment);
        }

        return $result;
    }

    /**
     * @param ShipmentRepositoryInterface $subject
     * @param ShipmentInterface $shipment
     *
     * @return ShipmentInterface
     */
    public function afterSave(ShipmentRepositoryInterface $subject, ShipmentInterface $shipment): ShipmentInterface
    {
        if ($this->shipmentHelper->isShipmentPolkurierShipment($shipment)
            && $polkurierOrder = $shipment->getExtensionAttributes()?->getPolkurierShipmentData()
        ) {
            $polkurierOrder->setParentShipmentId((int)$shipment->getId());
            $this->orderRepository->save($polkurierOrder);
        }

        return $shipment;
    }

    /**
     * @param ShipmentInterface $shipment
     *
     * @return ShipmentInterface
     */
    private function loadExtensionAttributes(ShipmentInterface $shipment): ShipmentInterface
    {
        $extensionAttributes = $shipment->getExtensionAttributes() ?: $this->extensionFactory->create();
        $polkurierOrder = $this->orderFactory->create();
        if ($this->shipmentHelper->isShipmentPolkurierShipment($shipment)) {
            $this->orderRepository->findOneByOrderId((int)$shipment->getOrder()->getId());
        }
        $extensionAttributes->setPolkurierShipmentData($polkurierOrder);
        $shipment->setExtensionAttributes($extensionAttributes);

        return $shipment;
    }
}
