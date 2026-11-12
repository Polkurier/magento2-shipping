<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer\Checkout;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Api\QuotePointRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\Polkurier;

/**
 * Carries the pickup point chosen in checkout onto the placed order.
 *
 * The order is placed and paid by then: a failure here must not show the customer an error. It's
 * logged, and the merchant can set the point on the order.
 *
 * Event: sales_model_service_quote_submit_success
 */
readonly class CopyPickupPointToOrderObserver implements ObserverInterface
{
    public function __construct(
        private OrderPointRepositoryInterface $orderPointRepository,
        private QuotePointRepositoryInterface $quotePointRepository,
        private Logger $logger,
    ) {}

    public function execute(Observer $observer): void
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getData('quote');
        /** @var Order $order */
        $order = $observer->getEvent()->getData('order');
        try {
            $point = $this->quotePointRepository->getForQuote((int)$quote->getId());
            if ($point === null || !$order->getEntityId()) {
                return;
            }
            if ((string)$order->getShippingMethod() === Polkurier::buildShippingMethod($point->getMethodCode())) {
                $this->orderPointRepository->copyFromQuote($point, (int)$order->getEntityId());
            }
        } catch (\Throwable $e) {
            $this->logger->error('Pickup point not copied to the order', [
                'order' => $order->getIncrementId(),
                'exception' => $e,
            ]);
        }
    }
}
