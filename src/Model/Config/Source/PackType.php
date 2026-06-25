<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Webservice\Enum\PackTypeEnum;

readonly class PackType implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => PackTypeEnum::STANDARD, 'label' => __('Standard')],
            ['value' => PackTypeEnum::NON_STANDARD, 'label' => __('Non standard')],
            ['value' => PackTypeEnum::PALETTE, 'label' => __('Palette')],
            ['value' => PackTypeEnum::HALF_PALETTE, 'label' => __('Half palette')],
            ['value' => PackTypeEnum::LONG, 'label' => __('Long')],
        ];
    }
}
