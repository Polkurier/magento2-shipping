<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Adminhtml\Edit;

class SaveButton extends GenericButton
{
    /**
     * @return array
     */
    public function getButtonData(): array
    {
        return [
            'label' => \__('Save'),
            'class' => 'save primary',
            'sort_order' => 40,
            'data_attribute' => [
                'mage-init' => [
                    'button' => [
                        'event' => 'save',
                    ],
                ],
                'form-role' => 'save',
            ],
        ];
    }
}
