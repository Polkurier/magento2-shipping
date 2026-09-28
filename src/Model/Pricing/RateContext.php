<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Pricing;

/**
 * The cart figures a price can depend on.
 */
final readonly class RateContext
{
    public function __construct(
        public float $subtotal,
        public float $weight,
    ) {}
}
