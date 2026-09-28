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

/**
 * Carries the pickup point chosen in checkout onto the placed order.
 *
 * Event: sales_model_service_quote_submit_success
 */
class CopyPickupPointToOrder implements ObserverInterface
{
    public function __construct(
        private readonly OrderPointStore $pointStore,
    ) {}

    public function execute(Observer $observer): void
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getData('quote');
        /** @var Order $order */
        $order = $observer->getEvent()->getData('order');
        $point = $this->pointStore->getForQuote((int)$quote->getId());
        if ($point === null || !$order->getEntityId()) {
            return;
        }
        if ((string)$order->getShippingMethod() === Polkurier::CODE . '_' . $point->getMethodCode()) {
            $this->pointStore->copyToOrder($point, (int)$order->getEntityId());
        }
    }
}
