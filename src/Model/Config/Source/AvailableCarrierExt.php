<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

readonly class AvailableCarrierExt extends AvailableCarrier
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = parent::toOptionArray();
        array_unshift($options, ['value' => '', 'label' => \__('Choose a carrier...')]);

        return $options;
    }
}
