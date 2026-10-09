<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier\Pricing;

use Polkurier\Shipping\Api\Data\CarrierConfigInterface;

/**
 * price_info: {"price": 12.99}
 */
readonly class FixedPriceCalculator implements PriceCalculatorInterface
{
    public const string FIELD_PRICE = 'price';

    public function calculate(CarrierConfigInterface $config, float $subtotal, float $weight): ?float
    {
        $price = $config->getPriceInfo()[self::FIELD_PRICE] ?? null;

        return \is_numeric($price) && (float)$price >= 0 ? (float)$price : null;
    }
}
