<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Adminhtml;

use Magento\Sales\Helper\Admin;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;

/**
 * Opens the module's links in order and shipment comments in a new tab. Magento keeps a link's href
 * and class in a comment but drops its target, so a link of the module's class gets it back once
 * the comment is escaped.
 */
class ExternalCommentLinksPlugin
{
    /**
     * @param string|array<mixed> $result
     *
     * @return string|array<mixed>
     */
    public function afterEscapeHtmlWithLinks(Admin $subject, string|array $result): string|array
    {
        if (!\is_string($result) || !\str_contains($result, NativeFollowUpService::EXTERNAL_LINK)) {
            return $result;
        }

        return (string)\preg_replace(
            '/<a\b(?=[^>]*\bclass="' . NativeFollowUpService::EXTERNAL_LINK . '")([^>]*)>/',
            '<a$1 target="_blank" rel="noopener">',
            $result,
        );
    }
}
