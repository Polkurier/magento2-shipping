<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * The button that submits a form; a form whose submit does something else than save names it in a
 * virtual type in adminhtml/di.xml.
 */
readonly class SaveButton implements ButtonProviderInterface
{
    public function __construct(
        private string $label = 'Save',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => \__($this->label),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90,
        ];
    }
}
