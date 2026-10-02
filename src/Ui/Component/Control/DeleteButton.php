<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Deletes the entry being edited, once confirmed, by POST to the path given: the form's own
 * delete, as the list's row action is. Configured per form as a virtual type in adminhtml/di.xml.
 */
class DeleteButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $url,
        private readonly Json $json,
        private readonly string $path,
        private readonly string $message,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $id = (int)$this->request->getParam('id');
        if ($id === 0) {
            return [];
        }

        return [
            'label' => \__('Delete'),
            'class' => 'delete',
            'on_click' => \sprintf(
                'deleteConfirm(%s, %s, {data: {}}); return false;',
                $this->json->serialize((string)\__($this->message)),
                $this->json->serialize($this->url->getUrl($this->path, ['id' => $id])),
            ),
            'sort_order' => 20,
        ];
    }
}
