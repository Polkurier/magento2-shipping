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
        foreach ($result->getItems() as $order) {
            $this->attach($order);
        }

        return $result;
    }

    private function attach(OrderInterface $order): void
    {
        $extension = $order->getExtensionAttributes() ?? $this->extensionFactory->create();
        if ($extension->getPolkurierPoint() !== null || !$order->getEntityId()) {
            return;
        }
        $point = $this->pointStore->getForOrder((int)$order->getEntityId());
        if ($point !== null) {
            $extension->setPolkurierPoint($point);
            $order->setExtensionAttributes($extension);
        }
    }
}
