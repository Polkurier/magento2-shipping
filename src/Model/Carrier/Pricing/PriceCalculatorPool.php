<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier\Pricing;

use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Logger\Logger;

/**
 * Prices a carrier with the calculator its pricing model names. Calculators are registered in di.xml.
 */
readonly class PriceCalculatorPool implements PriceCalculatorInterface
{
    /**
     * @param array<string, PriceCalculatorInterface> $calculators keyed by
     *     {@see \Polkurier\Shipping\Enum\CarrierConfig\PricingModelEnum} value
     */
    public function __construct(
        private Logger $logger,
        private array $calculators = [],
    ) {}

    public function calculate(CarrierConfigInterface $config, float $subtotal, float $weight): ?float
    {
        $calculator = $this->calculators[$config->getPricingModel()] ?? null;
        if (!$calculator instanceof PriceCalculatorInterface) {
            // Left out of checkout and logged: a setting went wrong, not a carrier's refusal.
            $this->logger->warning('Polkurier carrier has a pricing model no calculator knows', [
                'carrier' => $config->getCarrierCode(),
                'pricing_model' => $config->getPricingModel(),
            ]);

            return null;
        }

        return $calculator->calculate($config, $subtotal, $weight);
    }
}
