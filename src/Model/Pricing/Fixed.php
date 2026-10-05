<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Pricing;

use Polkurier\Shipping\Api\Data\CarrierConfigInterface;

/**
 * price_info: {"price": 12.99}
 */
readonly class Fixed implements PriceCalculatorInterface
{
    public const string PRICE = 'price';

    public function calculate(CarrierConfigInterface $config, RateContext $context): ?float
    {
        $price = $config->getPriceInfo()[self::PRICE] ?? null;

        return \is_numeric($price) && (float)$price >= 0 ? (float)$price : null;
    }
}
