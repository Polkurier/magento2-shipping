<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

/**
 * What a parcel of a pack template weighs: what's in it, or its template's weight. A parcel with a
 * product that has no weight always takes its template's weight, since what's in it can't be
 * weighed; a parcel without a template always weighs what's in it.
 */
enum ParcelWeightEnum: string
{
    case Products = 'products';
    case Template = 'template';

    /**
     * @param float|null $templateWeight null for a parcel without a template
     * @param float $contentsWeight what the products in the parcel weigh, in kilograms
     * @param bool $weighed whether the parcel holds products, each with a weight
     */
    public function weigh(?float $templateWeight, float $contentsWeight, bool $weighed): float
    {
        if ($templateWeight === null || $templateWeight <= 0) {
            return \round($contentsWeight, 3);
        }

        return $this === self::Products && $weighed ? \round($contentsWeight, 3) : $templateWeight;
    }
}
