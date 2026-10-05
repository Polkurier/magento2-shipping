<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxFeePaymentTypeEnum;

/**
 * How a payout fee is paid.
 */
readonly class FeePaymentType extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            MoneyBoxFeePaymentTypeEnum::MoneyBox->value => (string)\__('From the money box'),
            MoneyBoxFeePaymentTypeEnum::Invoice->value => (string)\__('On the invoice'),
            MoneyBoxFeePaymentTypeEnum::Prepaid->value => (string)\__('From the prepaid balance'),
            MoneyBoxFeePaymentTypeEnum::Online->value => (string)\__('Online payment'),
            MoneyBoxFeePaymentTypeEnum::Free->value => (string)\__('Free'),
        ];
    }
}
