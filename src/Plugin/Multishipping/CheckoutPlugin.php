<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Multishipping;

use Magento\Framework\Exception\LocalizedException;
use Magento\Multishipping\Model\Checkout\Type\Multishipping;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Checkout\MultishippingPoints;
use Polkurier\Shipping\Model\Checkout\PointSelection;

/**
 * Pickup points in the multi-address checkout: stored per address with the shipping methods,
 * and required again before the orders are placed. The points come from the shipping step's post,
 * through {@see MultishippingPoints}.
 */
class CheckoutPlugin
{
    public function __construct(
        private readonly MultishippingPoints $points,
        private readonly PointSelection $pointSelection,
    ) {}

    /**
     * @param array<int|string, string> $methods shipping method codes by quote address id
     * @return null
     * @throws LocalizedException when a method needs a point and none, or an unknown one, was chosen
     */
    public function beforeSetShippingMethods(Multishipping $subject, $methods)
    {
        $quote = $subject->getQuote();
        foreach ($quote->getAllShippingAddresses() as $address) {
            $addressId = (int)$address->getId();
            $method = (string)($methods[$addressId] ?? '');
            $code = Polkurier::methodCodeOf($method);
            $this->pointSelection->select(
                $quote,
                $code !== null ? Polkurier::CODE : $method,
                (string)$code,
                $this->points->get($addressId),
                $addressId,
            );
        }

        return null;
    }

    /**
     * @return null
     * @throws LocalizedException
     */
    public function beforeCreateOrders(Multishipping $subject)
    {
        $quote = $subject->getQuote();
        foreach ($quote->getAllShippingAddresses() as $address) {
            $code = Polkurier::methodCodeOf((string)$address->getShippingMethod());
            if ($code !== null) {
                $this->pointSelection->requirePoint($quote, $code, (int)$address->getId());
            }
        }

        return null;
    }
}
