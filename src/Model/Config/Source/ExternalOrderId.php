<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Enum\Config\ExternalOrderIdEnum;

/**
 * What a Polkurier order is identified by: the Magento order's number or its shipment's.
 */
readonly class ExternalOrderId extends AbstractOptions
{
    public function toArray(): array
    {
        $options = [];
        foreach (ExternalOrderIdEnum::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
