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
 * Loads the orders the module works with. A Polkurier shipment is only started for an order placed
 * with a Polkurier delivery method (getForPolkurier()): the module never ships orders of other
 * carriers, whose integrations handle them. Anything that follows from an existing shipment loads
 * its order however it was placed (get()).
 */
readonly class OrderLoader
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * An order placed with a Polkurier method, to start a Polkurier shipment for.
     *
     * @throws LocalizedException when the order doesn't exist or wasn't placed with Polkurier
     */
    public function getForPolkurier(int $orderId): Order
    {
        $order = $this->get($orderId);
        if (!Polkurier::isOrderOf($order)) {
            throw new LocalizedException(__(
                'Order #%1 wasn\'t placed with a Polkurier delivery method, so it isn\'t shipped with Polkurier.',
                $order->getIncrementId(),
            ));
        }

        return $order;
    }

    /**
     * The order if it was placed with a Polkurier method, or null.
     */
    public function findForPolkurier(int $orderId): ?Order
    {
        $order = $this->find($orderId);

        return $order !== null && Polkurier::isOrderOf($order) ? $order : null;
    }

    /**
     * Any order, such as the one a Polkurier shipment was made for.
     *
     * @throws LocalizedException when the order doesn't exist
     */
    public function get(int $orderId): Order
    {
        return $this->find($orderId) ?? throw new LocalizedException(__('The order no longer exists.'));
    }

    /**
     * Any order, or null when there's none with the id.
     */
    public function find(int $orderId): ?Order
    {
        if ($orderId <= 0) {
            return null;
        }
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException) {
            return null;
        }

        return $order instanceof Order ? $order : null;
    }
}
