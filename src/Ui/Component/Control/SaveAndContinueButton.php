<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Saves the form and comes back to it. One virtual type per form, naming it.
 */
class SaveAndContinueButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly string $formName = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => \__('Save and Continue Edit'),
            'class' => 'save',
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [[
                            'targetName' => $this->formName . '.' . $this->formName,
                            'actionName' => 'save',
                            'params' => [true, ['back' => 'continue']],
                        ]],
                    ],
                ],
            ],
            'sort_order' => 80,
        ];
    }
}
