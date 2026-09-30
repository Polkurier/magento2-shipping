<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * A toolbar button that runs an action by POST, with the form key, instead of following a link.
 *
 * For actions that change data; configured per button as a virtual type in adminhtml/di.xml.
 */
class PostButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly UrlInterface $url,
        private readonly Json $json,
        private readonly string $label,
        private readonly string $path,
        private readonly string $class = 'secondary',
        private readonly int $sortOrder = 20,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        // "data" must reach dataPost as an object: it adds the form key to it as a property.
        $post = $this->json->serialize(['action' => $this->url->getUrl($this->path), 'data' => new \stdClass()]);

        return [
            'label' => \__($this->label),
            'class' => $this->class,
            'on_click' => \sprintf("require(['mage/dataPost'], function (dataPost) { dataPost().postData(%s); }); return false;", $post),
            'sort_order' => $this->sortOrder,
        ];
    }
}
