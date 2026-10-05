<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Pricing;

use Polkurier\Shipping\Api\Data\CarrierConfigInterface;

/**
 * Prices a carrier with the calculator its pricing model names. Calculators are registered in di.xml.
 */
readonly class PriceCalculatorPool implements PriceCalculatorInterface
{
    /**
     * @param array<string, PriceCalculatorInterface> $calculators keyed by PricingModelEnum value
     */
    public function __construct(
        private array $calculators = [],
    ) {}

    public function calculate(CarrierConfigInterface $config, RateContext $context): ?float
    {
        $calculator = $this->calculators[$config->getPricingModel()] ?? null;

        return $calculator instanceof PriceCalculatorInterface ? $calculator->calculate($config, $context) : null;
    }
}
