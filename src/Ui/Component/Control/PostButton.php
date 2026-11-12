<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * A toolbar button that runs an action by POST, with the form key, instead of following a link.
 *
 * For actions that change data; configured per button as a virtual type in adminhtml/di.xml.
 */
readonly class PostButton implements ButtonProviderInterface
{
    public function __construct(
        private UrlInterface $url,
        private OnClickScriptBuilder $onClickScriptBuilder,
        private string $label,
        private string $path,
        private string $class = 'secondary',
        private int $sortOrder = 20,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __($this->label),
            'class' => $this->class,
            'on_click' => $this->onClickScriptBuilder->buildPost($this->url->getUrl($this->path)),
            'sort_order' => $this->sortOrder,
        ];
    }
}
