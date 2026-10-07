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
readonly class PointFunction extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            PointFunctionEnum::Collect->value => (string)__('Hand out parcels'),
            PointFunctionEnum::Send->value => (string)__('Accept parcels for sending'),
            PointFunctionEnum::Cod->value => (string)__('Take cash on delivery'),
        ];
    }
}
