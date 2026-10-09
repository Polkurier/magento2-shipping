<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum\Config;

/**
 * How a parcel of a pack template is weighed: its contents plus the packaging's tare, or the
 * template's weight. A parcel holding a product without a weight always takes the template's
 * weight, since its contents can't be weighed. A parcel without a template always weighs what its
 * contents weigh.
 */
enum ParcelWeightEnum: string
{
    case Products = 'products';
    case Template = 'template';

    /**
     * @param float|null $templateWeight null for a parcel without a template
     * @param float $contentsWeight what the products in the parcel weigh, in kilograms
     * @param bool $weighed whether the parcel holds products, each with a weight
     * @param float|null $tare what the template's packaging weighs, already included in the
     *     template's weight
     */
    public function weigh(?float $templateWeight, float $contentsWeight, bool $weighed, ?float $tare = null): float
    {
        if ($templateWeight === null || $templateWeight <= 0) {
            return \round($contentsWeight, 3);
        }

        return $this === self::Products && $weighed
            ? \round($contentsWeight + \max(0.0, (float)$tare), 3)
            : $templateWeight;
    }

    public function label(): string
    {
        return match ($this) {
            self::Products => (string)__('What the products in it weigh'),
            self::Template => (string)__('The pack template\'s weight'),
        };
    }
}
