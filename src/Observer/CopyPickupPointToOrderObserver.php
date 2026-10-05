<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\OrderPointStore;
use Psr\Log\LoggerInterface;

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
        private OrderPointStore $pointStore,
        private LoggerInterface $logger,
    ) {}

    public function execute(Observer $observer): void
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getData('quote');
        /** @var Order $order */
        $order = $observer->getEvent()->getData('order');
        try {
            $point = $this->pointStore->getForQuote((int)$quote->getId());
            if ($point === null || !$order->getEntityId()) {
                return;
            }
            if ((string)$order->getShippingMethod() === Polkurier::CODE . '_' . $point->getMethodCode()) {
                $this->pointStore->copyToOrder($point, (int)$order->getEntityId());
            }
        } catch (\Throwable $e) {
            $this->logger->error('Pickup point not copied to the order', [
                'order' => $order->getIncrementId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
