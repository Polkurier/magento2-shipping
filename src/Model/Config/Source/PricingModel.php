<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Enum\CarrierConfig\PricingModelEnum;

/**
 * Checkout pricing models.
 */
readonly class PricingModel extends AbstractOptions
{
    public function toArray(): array
    {
        $models = [];
        foreach (PricingModelEnum::cases() as $model) {
            $models[$model->value] = $model->label();
        }

        return $models;
    }
}
