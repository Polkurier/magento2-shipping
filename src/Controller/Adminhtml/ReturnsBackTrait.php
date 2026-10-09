<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml;

use Magento\Framework\Controller\Result\Redirect;

/**
 * For the admin actions of the module's grids, rows and buttons: goes back to the page the action
 * was started from, rather than to a page of the action's own choosing. That page is the one the
 * browser names, or the one a form opened on the way carried along (the "uenc" parameter). The
 * fallback is used only when there's no such page, or when that page is gone with the action (a
 * shipment discarded, an entry deleted).
 *
 * For controllers extending {@see \Magento\Backend\App\Action}.
 */
trait ReturnsBackTrait
{
    /**
     * @param array<string, mixed> $fallbackParams
     * @param string[] $gone paths of pages the action did away with, e.g. "polkurier/shipment/view/id/5/"
     */
    protected function returnBack(string $fallbackPath, array $fallbackParams = [], array $gone = []): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $referer = (string)$this->_redirect->getRefererUrl();
        // Only an admin page will do. For a missing or outside referer, Magento falls back to the
        // site's base address, which with a custom admin URL is the storefront's.
        $admin = \rtrim((string)$this->_backendUrl->getBaseUrl(), '/')
            . '/' . $this->_backendUrl->getAreaFrontName() . '/';
        $usable = \str_starts_with($referer, $admin);
        foreach ($gone as $path) {
            $usable = $usable && !\str_contains($referer, $path);
        }

        return $usable ? $redirect->setUrl($referer) : $redirect->setPath($fallbackPath, $fallbackParams);
    }
}
