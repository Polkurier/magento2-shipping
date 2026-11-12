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
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\QuotePointRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\PickupPoint\PointDescriptionBuilder;

/**
 * Adds the pickup point chosen in checkout to the shipping description of the order being placed,
 * before it's saved and its e-mail sent (see {@see PointDescriptionBuilder}).
 *
 * Events: sales_model_service_quote_submit_before (one order, with "quote") and, registered by
 * polkurier/magento2-multishipping, checkout_type_multishipping_create_orders_single (each
 * multi-address order, with its "address").
 * A failure is logged: the order goes through as it is, with the point still copied to it.
 */
readonly class DescribePickupPointObserver implements ObserverInterface
{
    public function __construct(
        private QuotePointRepositoryInterface $quotePointRepository,
        private PointDescriptionBuilder $pointDescriptionBuilder,
        private Logger $logger,
    ) {}

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        $quote = $observer->getEvent()->getData('quote');
        $address = $observer->getEvent()->getData('address');
        if (!$order instanceof Order || !$quote instanceof Quote) {
            return;
        }
        $methodCode = Polkurier::extractMethodCode((string)$order->getShippingMethod());
        if ($methodCode === null) {
            return;
        }
        try {
            $point = $this->quotePointRepository->getForQuote(
                (int)$quote->getId(),
                $address instanceof Address ? (int)$address->getId() : 0,
            );
            if ($point === null || $point->getMethodCode() !== $methodCode) {
                return;
            }
            $order->setShippingDescription($this->pointDescriptionBuilder->describe(
                (string)$order->getShippingDescription(),
                $point->getPointId(),
                $point->getPointLabel(),
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Pickup point not added to the shipping description', [
                'order' => $order->getIncrementId(),
                'exception' => $e,
            ]);
        }
    }
}
