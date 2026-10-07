<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Comment;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Controller\Webhook\Shipment\Update as WebhookAction;

/**
 * The comment under the status webhook's secret: the URL to give the webhook in the Polkurier
 * panel, on the default store view's secure base URL.
 */
readonly class WebhookUrl implements CommentInterface
{
    public function __construct(
        private StoreManagerInterface $storeManager,
        private Escaper $escaper,
    ) {}

    /**
     * @param string $elementValue
     */
    public function getCommentText($elementValue): string
    {
        // Built on the store's base URL: in the admin, Magento's URLs are the admin's own.
        $store = $this->storeManager->getDefaultStoreView();
        $base = $store instanceof Store ? $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true) : '';

        return (string)__(
            'In the Polkurier panel, add a webhook with the URL %1 and paste its secret here.',
            '<code>' . $this->escaper->escapeHtml($base . WebhookAction::PATH) . '</code>',
        );
    }
}
