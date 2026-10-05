<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Model\Carrier\CarrierNames;

/**
 * Carriers by name, for grids and forms that keep a carrier code: the code stays the value.
 */
readonly class CarrierName extends AbstractOptions
{
    public function __construct(
        private CarrierNames $carrierNames,
    ) {}

    public function toArray(): array
    {
        return $this->carrierNames->getAll();
    }
}
