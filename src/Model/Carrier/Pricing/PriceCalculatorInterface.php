<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier\Pricing;

use Polkurier\Shipping\Api\Data\CarrierConfigInterface;

/**
 * Works out a carrier's checkout price by one pricing model; null when it offers none.
 */
interface PriceCalculatorInterface
{
    /**
     * @param CarrierConfigInterface $config
     * @param float $subtotal the cart's value, in the base currency
     * @param float $weight the cart's weight, in kilograms
     *
     * @return float|null the price, or null when the configuration does not price this cart,
     *                    in which case the method is not offered
     */
    public function calculate(CarrierConfigInterface $config, float $subtotal, float $weight): ?float;
}
