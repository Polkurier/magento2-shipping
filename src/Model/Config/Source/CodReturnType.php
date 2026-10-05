<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\Order\CodReturnTypeEnum;

/**
 * Where cash on delivery is paid out to.
 */
readonly class CodReturnType extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            CodReturnTypeEnum::BankAccount->value => (string)\__('Bank account'),
            CodReturnTypeEnum::MoneyBox->value => (string)\__('Polkurier money box'),
            CodReturnTypeEnum::PostOffice->value => (string)\__('Postal order'),
        ];
    }
}
