<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;

/**
 * Carriers by name, for grids and forms that keep a carrier code: the code stays the value.
 */
readonly class CarrierName extends AbstractOptions
{
    public function __construct(
        private CarrierNameProvider $carrierNameProvider,
    ) {}

    public function toArray(): array
    {
        return $this->carrierNameProvider->getAll();
    }
}
