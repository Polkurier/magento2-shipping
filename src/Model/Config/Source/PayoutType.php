<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxPayoutTypeEnum;

/**
 * How the money box pays out.
 */
readonly class PayoutType extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            MoneyBoxPayoutTypeEnum::Account->value => (string)__('Bank transfer'),
            MoneyBoxPayoutTypeEnum::Postal->value => (string)__('Postal order'),
        ];
    }
}
