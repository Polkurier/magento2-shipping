<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Sales;

use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\Data\ShippingChoiceInterface;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Api\ShippingChoiceRepositoryInterface;
use Polkurier\Shipping\Model\Sdk\ApplicationIdProvider;

/**
 * Exposes the order's pickup point as its polkurier_point extension attribute. When the order's
 * shipping was changed to match its Polkurier order, it also exposes the shipping the customer
 * chose as polkurier_shipping_choice. Every order carries the installation's id as
 * polkurier_application_id: a Polkurier order made for it elsewhere, e.g. from an import of the
 * shop's orders, carries that id to be linked to it
 * ({@see \Polkurier\Shipping\Model\Shipment\Status\TrackAdoptionService}).
 */
readonly class OrderRepositoryPlugin
{
    public function __construct(
        private OrderPointRepositoryInterface $orderPointRepository,
        private ShippingChoiceRepositoryInterface $shippingChoiceRepository,
        private OrderExtensionFactory $extensionFactory,
        private ApplicationIdProvider $applicationIdProvider,
    ) {}

    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $order): OrderInterface
    {
        if ($order->getEntityId()) {
            $orderId = (int)$order->getEntityId();
            $this->attach(
                $order,
                $this->orderPointRepository->getForOrder($orderId),
                $this->shippingChoiceRepository->getForOrder($orderId),
            );
        }

        return $order;
    }

    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $result,
    ): OrderSearchResultInterface {
        $orderIds = [];
        foreach ($result->getItems() as $order) {
            if ($order->getEntityId()) {
                $orderIds[] = (int)$order->getEntityId();
            }
        }
        $points = $this->orderPointRepository->getForOrders($orderIds);
        $choices = $this->shippingChoiceRepository->getForOrders($orderIds);
        foreach ($result->getItems() as $order) {
            $orderId = (int)$order->getEntityId();
            $this->attach($order, $points[$orderId] ?? null, $choices[$orderId] ?? null);
        }

        return $result;
    }

    private function attach(OrderInterface $order, ?OrderPointInterface $point, ?ShippingChoiceInterface $choice): void
    {
        $extension = $order->getExtensionAttributes() ?? $this->extensionFactory->create();
        $extension->setPolkurierApplicationId($this->applicationIdProvider->get());
        if ($point !== null) {
            $extension->setPolkurierPoint($point);
        }
        if ($choice !== null) {
            $extension->setPolkurierShippingChoice($choice);
        }
        $order->setExtensionAttributes($extension);
    }
}
