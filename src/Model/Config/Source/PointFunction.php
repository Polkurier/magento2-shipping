<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\Courier\PointFunctionEnum;

/**
 * What a pickup point must allow to be shown.
 */
class PointFunction extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            PointFunctionEnum::Collect->value => (string)\__('Collect parcels'),
            PointFunctionEnum::Send->value => (string)\__('Send parcels'),
            PointFunctionEnum::Cod->value => (string)\__('Collect cash on delivery'),
        ];
    }
}
