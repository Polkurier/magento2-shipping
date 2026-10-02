<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Sales;

use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\Data\ShippingChoiceInterface;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\ShippingChoiceStore;

/**
 * Exposes the order's pickup point as its polkurier_point extension attribute, and, when the
 * order's shipping was changed to what its Polkurier order went with, the shipping the customer
 * chose as polkurier_shipping_choice.
 */
class OrderRepositoryPlugin
{
    public function __construct(
        private readonly OrderPointStore $pointStore,
        private readonly ShippingChoiceStore $choices,
        private readonly OrderExtensionFactory $extensionFactory,
    ) {}

    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $order): OrderInterface
    {
        if ($order->getEntityId()) {
            $orderId = (int)$order->getEntityId();
            $this->attach($order, $this->pointStore->getForOrders([$orderId])[$orderId] ?? null, $this->choices->get($orderId));
        }

        return $order;
    }

    public function afterGetList(OrderRepositoryInterface $subject, OrderSearchResultInterface $result): OrderSearchResultInterface
    {
        $orderIds = [];
        foreach ($result->getItems() as $order) {
            if ($order->getEntityId()) {
                $orderIds[] = (int)$order->getEntityId();
            }
        }
        $points = $this->pointStore->getForOrders($orderIds);
        $choices = $this->choices->getForOrders($orderIds);
        foreach ($result->getItems() as $order) {
            $orderId = (int)$order->getEntityId();
            $this->attach($order, $points[$orderId] ?? null, $choices[$orderId] ?? null);
        }

        return $result;
    }

    private function attach(OrderInterface $order, ?OrderPointInterface $point, ?ShippingChoiceInterface $choice): void
    {
        if ($point === null && $choice === null) {
            return;
        }
        $extension = $order->getExtensionAttributes() ?? $this->extensionFactory->create();
        if ($point !== null) {
            $extension->setPolkurierPoint($point);
        }
        if ($choice !== null) {
            $extension->setPolkurierShippingChoice($choice);
        }
        $order->setExtensionAttributes($extension);
    }
}
