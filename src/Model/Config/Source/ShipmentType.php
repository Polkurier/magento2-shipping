<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Webservice\Enum\ShipmentTypeEnum;

readonly class ShipmentType implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => ShipmentTypeEnum::ENVELOPE->value, 'label' => __('Envelope')],
            ['value' => ShipmentTypeEnum::BOX->value, 'label' => __('Box')],
            ['value' => ShipmentTypeEnum::PALETTE->value, 'label' => __('Palette')],
        ];
    }
}
