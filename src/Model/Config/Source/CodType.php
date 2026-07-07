<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Webservice\Enum\CodTypeEnum;

readonly class CodType implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => CodTypeEnum::STANDARD->value, 'label' => \__('STANDARD - Refund within 5-7 business days depending on carrier')],
            ['value' => CodTypeEnum::ONE_DAY->value, 'label' => \__('Refund within 1 business day of delivery')],
            ['value' => CodTypeEnum::FOUR_DAYS->value, 'label' => \__('Refund within 4 business days of delivery')],
            ['value' => CodTypeEnum::SIXTEEN_DAYS->value, 'label' => \__('Refund within 16 business days of delivery')],
        ];
    }
}
