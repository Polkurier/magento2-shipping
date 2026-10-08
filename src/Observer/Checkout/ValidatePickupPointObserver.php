<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer\Checkout;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\PickupPoint\PointSelectionRecorder;

/**
 * Refuses to place an order whose delivery method needs a pickup point when none was chosen for
 * that method, whatever route the order came in by.
 *
 * Event: sales_model_service_quote_submit_before
 */
readonly class ValidatePickupPointObserver implements ObserverInterface
{
    public function __construct(
        private PointSelectionRecorder $pointSelectionRecorder,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getData('quote');
        $methodCode = Polkurier::methodCodeOf((string)$quote->getShippingAddress()->getShippingMethod());
        if ($methodCode !== null && !$quote->isVirtual()) {
            $this->pointSelectionRecorder->requirePoint($quote, $methodCode);
        }
    }
}
