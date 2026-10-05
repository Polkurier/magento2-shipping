<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Model\Shipment\ExternalOrderIdEnum;

readonly class ExternalOrderId extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            ExternalOrderIdEnum::Shipment->value => (string)\__('The Magento shipment\'s number'),
            ExternalOrderIdEnum::Order->value => (string)\__('The Magento order\'s number'),
        ];
    }
}
