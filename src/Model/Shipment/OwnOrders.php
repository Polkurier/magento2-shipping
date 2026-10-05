<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Model\Carrier\Polkurier;

/**
 * Loads an order to start a Polkurier shipment for. Only orders placed with a Polkurier method
 * qualify: the module never ships orders of other carriers, whose integrations handle them.
 */
readonly class OwnOrders
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * @throws LocalizedException when the order doesn't exist or wasn't placed with Polkurier
     */
    public function get(int $orderId): Order
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(\__('The order no longer exists.'), $e);
        }
        if (!$order instanceof Order || !Polkurier::isOrderOf($order)) {
            throw new LocalizedException(\__('Order #%1 wasn\'t placed with a Polkurier delivery method, so it isn\'t shipped with Polkurier.', $order->getIncrementId()));
        }

        return $order;
    }
}
