<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier\Pricing;

use Polkurier\Shipping\Api\Data\CarrierConfigInterface;

/**
 * Tiered pricing on one or two thresholds.
 *
 * price_info: {"tiers": [{"subtotal_from": 0, "weight_from": 0, "price": 14.99}, …]}, using only
 * the thresholds the model names. A tier applies when the cart reaches all of its thresholds; the
 * most specific applicable tier wins — the highest subtotal threshold, then the highest weight
 * threshold. A cart below every tier is not priced, so the method is not offered.
 */
readonly class TieredPriceCalculator implements PriceCalculatorInterface
{
    public const string FIELD_TIERS = 'tiers';
    public const string FIELD_SUBTOTAL_FROM = 'subtotal_from';
    public const string FIELD_WEIGHT_FROM = 'weight_from';
    public const string FIELD_PRICE = 'price';

    public function __construct(
        private bool $bySubtotal,
        private bool $byWeight,
    ) {}

    public function calculate(CarrierConfigInterface $config, RateContext $context): ?float
    {
        $best = null;
        foreach ((array)($config->getPriceInfo()[self::FIELD_TIERS] ?? []) as $tier) {
            if (!\is_array($tier) || !\is_numeric($tier[self::FIELD_PRICE] ?? null)) {
                continue;
            }
            $subtotalFrom = $this->bySubtotal ? (float)($tier[self::FIELD_SUBTOTAL_FROM] ?? 0) : 0.0;
            $weightFrom = $this->byWeight ? (float)($tier[self::FIELD_WEIGHT_FROM] ?? 0) : 0.0;
            if ($context->subtotal < $subtotalFrom || $context->weight < $weightFrom) {
                continue;
            }
            if ($best === null
                || $subtotalFrom > $best[0]
                || ($subtotalFrom === $best[0] && $weightFrom > $best[1])
            ) {
                $best = [$subtotalFrom, $weightFrom, (float)$tier[self::FIELD_PRICE]];
            }
        }

        return $best !== null && $best[2] >= 0 ? $best[2] : null;
    }
}
