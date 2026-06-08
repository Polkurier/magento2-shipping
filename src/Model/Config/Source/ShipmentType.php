<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Webservice\Api\Data\ShipmentTypeEnum;

class ShipmentType implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => ShipmentTypeEnum::ENVELOPE, 'label' => __('Envelope')],
            ['value' => ShipmentTypeEnum::BOX, 'label' => __('Box')],
            ['value' => ShipmentTypeEnum::PALETTE, 'label' => __('Palette')],
        ];
    }
}
