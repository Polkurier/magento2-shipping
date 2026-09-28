<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Multishipping;

use Magento\Multishipping\Block\Checkout\Overview;
use Magento\Quote\Model\Quote\Address\Rate;
use Polkurier\Shipping\Model\Carrier\Polkurier;

/**
 * The multi-address review step prints a method as "carrier title (method title)". The module's
 * methods have no carrier title, which would leave "(Paczkomat InPost)"; they get the same neutral
 * heading the shipping step uses. A copy of the rate is changed, never the rate itself.
 */
class OverviewPlugin
{
    public function afterGetShippingAddressRate(Overview $subject, mixed $result): mixed
    {
        if (!$result instanceof Rate || $result->getCarrier() !== Polkurier::CODE || (string)$result->getCarrierTitle() !== '') {
            return $result;
        }
        $rate = clone $result;
        $rate->setCarrierTitle((string)\__('Delivery'));

        return $rate;
    }
}
