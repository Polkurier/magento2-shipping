<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\Order\CodTypeEnum;

/**
 * Cash on delivery payout times. 16 days is left out: it can't be ordered through the module.
 */
class CodType extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            CodTypeEnum::Standard->value => (string)\__('Standard'),
            CodTypeEnum::OneDay->value => (string)\__('Next day'),
            CodTypeEnum::FourDays->value => (string)\__('Within 4 days'),
        ];
    }
}
