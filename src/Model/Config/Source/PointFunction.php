<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Webservice\Enum\PointFunctionEnum;

readonly class PointFunction implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => PointFunctionEnum::COD->value, 'label' => __('Cash on delivery handling')],
            ['value' => PointFunctionEnum::SEND->value, 'label' => __('Sending shipments')],
            ['value' => PointFunctionEnum::COLLECT->value, 'label' => __('Collecting shipments')],
        ];
    }
}
