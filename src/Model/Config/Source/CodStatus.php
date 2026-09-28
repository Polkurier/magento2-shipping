<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\Order\CodStatusEnum;

/**
 * Cash on delivery states reported by Polkurier.
 */
class CodStatus extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            CodStatusEnum::Waiting->value => (string)\__('Awaiting collection'),
            CodStatusEnum::InTransit->value => (string)\__('On its way to Polkurier'),
            CodStatusEnum::MoneyBox->value => (string)\__('In the money box'),
            CodStatusEnum::PaymentPending->value => (string)\__('Payout pending'),
            CodStatusEnum::Compensation->value => (string)\__('Compensation'),
            CodStatusEnum::Paid->value => (string)\__('Paid out'),
            CodStatusEnum::Settled->value => (string)\__('Settled'),
        ];
    }
}
