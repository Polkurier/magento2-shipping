<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Polkurier\Shipping\Model\Checkout\MultishippingPoints;

/**
 * Takes the pickup points posted with the multi-address shipping methods, just before the
 * controller saves the methods.
 *
 * Event: checkout_controller_multishipping_shipping_post
 */
class CaptureMultishippingPoints implements ObserverInterface
{
    public function __construct(
        private readonly MultishippingPoints $points,
    ) {}

    public function execute(Observer $observer): void
    {
        $request = $observer->getEvent()->getData('request');
        $this->points->setPosted($request instanceof RequestInterface ? (array)$request->getParam('pickup_point', []) : []);
    }
}
