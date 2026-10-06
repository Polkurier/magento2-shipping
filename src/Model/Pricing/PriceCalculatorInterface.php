<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Pricing;

use Polkurier\Shipping\Api\Data\CarrierConfigInterface;

/**
 * Works out a carrier's checkout price by one pricing model; null when it offers none.
 */
interface PriceCalculatorInterface
{
    /**
     * @return float|null the price, or null when the configuration does not price this cart,
     *                    in which case the method is not offered
     */
    public function calculate(CarrierConfigInterface $config, RateContext $context): ?float;
}
