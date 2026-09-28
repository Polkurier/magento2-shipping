<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order;

/**
 * Remembers on each multi-address order which address it was made for, which Magento does not
 * record, so its pickup point can be copied once the order is saved.
 *
 * Event: checkout_type_multishipping_create_orders_single
 */
class TagMultishippingOrder implements ObserverInterface
{
    public const string QUOTE_ADDRESS_ID = 'pickup_point_quote_address_id';

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        $address = $observer->getEvent()->getData('address');
        if ($order instanceof Order && $address instanceof Address) {
            $order->setData(self::QUOTE_ADDRESS_ID, (int)$address->getId());
        }
    }
}
