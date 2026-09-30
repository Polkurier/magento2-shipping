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
 * Copies each address's pickup point onto the order placed for it in the multi-address checkout.
 *
 * Event: checkout_submit_all_after (with "orders"; the regular checkout's has "order" and is left
 * to CopyPickupPointToOrder). The orders are placed by then: a failure is logged, never shown to
 * the customer, and doesn't stop the other orders.
 */
class CopyPickupPointsToOrders implements ObserverInterface
{
    public function __construct(
        private readonly OrderPointStore $pointStore,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(Observer $observer): void
    {
        $orders = $observer->getEvent()->getData('orders');
        $quote = $observer->getEvent()->getData('quote');
        if (!\is_array($orders) || !$quote instanceof Quote) {
            return;
        }
        foreach ($orders as $order) {
            if (!$order instanceof Order || !$order->getEntityId() || !$order->hasData(TagMultishippingOrder::QUOTE_ADDRESS_ID)) {
                continue;
            }
            try {
                $point = $this->pointStore->getForQuote((int)$quote->getId(), (int)$order->getData(TagMultishippingOrder::QUOTE_ADDRESS_ID));
                if ($point !== null && Polkurier::methodCodeOf((string)$order->getShippingMethod()) === $point->getMethodCode()) {
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
}
