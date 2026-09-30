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
use Polkurier\Shipping\Model\OrderPointStore;

/**
 * Exposes the pickup point chosen in checkout as the order's polkurier_point extension attribute.
 */
class OrderRepositoryPlugin
{
    public function __construct(
        private readonly OrderPointStore $pointStore,
        private readonly OrderExtensionFactory $extensionFactory,
    ) {}

    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $order): OrderInterface
    {
        $this->attach($order);

        return $order;
    }

    public function afterGetList(OrderRepositoryInterface $subject, OrderSearchResultInterface $result): OrderSearchResultInterface
    {
        $orderIds = [];
        foreach ($result->getItems() as $order) {
            if ($order->getEntityId() && $order->getExtensionAttributes()?->getPolkurierPoint() === null) {
                $orderIds[] = (int)$order->getEntityId();
            }
        }
        $points = $this->pointStore->getForOrders($orderIds);
        foreach ($result->getItems() as $order) {
            $point = $points[(int)$order->getEntityId()] ?? null;
            if ($point !== null) {
                $this->setPoint($order, $point);
            }
        }

        return $result;
    }

    private function attach(OrderInterface $order): void
    {
        if (!$order->getEntityId() || $order->getExtensionAttributes()?->getPolkurierPoint() !== null) {
            return;
        }
        $point = $this->pointStore->getForOrder((int)$order->getEntityId());
        if ($point !== null) {
            $this->setPoint($order, $point);
        }
    }

    private function setPoint(OrderInterface $order, OrderPointInterface $point): void
    {
        $extension = $order->getExtensionAttributes() ?? $this->extensionFactory->create();
        $extension->setPolkurierPoint($point);
        $order->setExtensionAttributes($extension);
    }
}
