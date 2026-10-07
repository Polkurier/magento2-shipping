<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum;

/**
 * What a parcel of a pack template weighs: what's in it with the packaging's tare, or its
 * template's weight. A parcel with a product that has no weight always takes its template's
 * weight, since what's in it can't be weighed; a parcel without a template always weighs what's in
 * it.
 */
enum ParcelWeightEnum: string
{
    case Products = 'products';
    case Template = 'template';

    /**
     * @param float|null $templateWeight null for a parcel without a template
     * @param float $contentsWeight what the products in the parcel weigh, in kilograms
     * @param bool $weighed whether the parcel holds products, each with a weight
     * @param float|null $tare what the template's packaging weighs, which the template's weight
     *     already holds
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
