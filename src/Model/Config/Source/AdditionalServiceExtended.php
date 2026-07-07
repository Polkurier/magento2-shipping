<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Enum\AdditionalServiceExtensionEnum;

readonly class AdditionalServiceExtended extends AdditionalService
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        $options = parent::toOptionArray();
        \array_unshift(
            $options,
            ['value' => AdditionalServiceExtensionEnum::COD->value, 'label' => \__('Cash on delivery')],
            ['value' => AdditionalServiceExtensionEnum::INSURANCE->value, 'label' => \__('Insurance')],
            ['value' => AdditionalServiceExtensionEnum::COVER_ADDRESS->value, 'label' => \__('Cover address')],
        );

        return $options;
    }
}
