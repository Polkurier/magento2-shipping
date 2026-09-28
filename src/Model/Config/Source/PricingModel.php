<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Model\Pricing\PricingModelEnum;

/**
 * Checkout pricing models.
 */
class PricingModel extends AbstractOptions
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
