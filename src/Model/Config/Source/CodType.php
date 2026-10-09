<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\Order\CodTypeEnum;

/**
 * Cash on delivery payout times. 16 days is left out: Polkurier's API refuses it.
 */
readonly class CodType extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            CodTypeEnum::Standard->value => (string)__('Standard time'),
            CodTypeEnum::OneDay->value => (string)__('Next day'),
            CodTypeEnum::FourDays->value => (string)__('Within 4 days'),
        ];
    }
}
