<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Multishipping;

use Magento\Framework\DataObject;
use Magento\Multishipping\Block\Checkout\Shipping;
use Magento\Quote\Model\Quote\Address;
use Polkurier\Shipping\Block\Multishipping\PickupPoint;
use Polkurier\Shipping\Model\Carrier\Polkurier;

/**
 * The multi-address shipping step: renders the pickup point picker under each address's delivery
 * methods, in the slot the step leaves for it, and keeps the Polkurier name out of its headings.
 */
class ShippingStepPlugin
{
    public function afterGetItemsBoxTextAfter(Shipping $subject, string $result, DataObject $address): string
    {
        $picker = $subject->getChildBlock('pickup_point');
        if (!$picker instanceof PickupPoint || !$address instanceof Address) {
            return $result;
        }

        return $result . $picker->setAddress($address)->toHtml();
    }

    /**
     * The step heads each carrier's methods with the carrier's configured title, or its code when
     * there is none. Either would show Polkurier to the customer; a neutral heading is used instead.
     *
     * @param string $carrierCode
     */
    public function afterGetCarrierName(Shipping $subject, mixed $result, $carrierCode): mixed
    {
        return $carrierCode === Polkurier::CODE ? (string)\__('Delivery') : $result;
    }
}
