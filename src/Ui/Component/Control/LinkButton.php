<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * A toolbar button that opens a page, shown only to admins allowed to open it.
 *
 * Configured per button as a virtual type in adminhtml/di.xml.
 */
class LinkButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly UrlInterface $url,
        private readonly AuthorizationInterface $authorization,
        private readonly string $label,
        private readonly string $path,
        private readonly string $resource,
        private readonly string $class = 'primary',
        private readonly int $sortOrder = 10,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        if (!$this->authorization->isAllowed($this->resource)) {
            return [];
        }

        return [
            'label' => \__($this->label),
            'class' => $this->class,
            'on_click' => \sprintf("setLocation('%s')", $this->url->getUrl($this->path)),
            'sort_order' => $this->sortOrder,
        ];
    }
}
