<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier\Pricing;

use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Enum\PricingModelEnum;
use Psr\Log\LoggerInterface;

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
        private ?LoggerInterface $logger = null,
    ) {}

    public function calculate(CarrierConfigInterface $config, RateContext $context): ?float
    {
        $calculator = $this->calculators[$config->getPricingModel()] ?? null;
        if (!$calculator instanceof PriceCalculatorInterface) {
            // Left out of checkout: said, as it's a setting that went wrong, not a carrier's refusal.
            $this->logger?->warning('Polkurier carrier has a pricing model no calculator knows', [
                'carrier' => $config->getCarrierCode(),
                'pricing_model' => $config->getPricingModel(),
            ]);

            return null;
        }

        return $calculator->calculate($config, $context);
    }
}
