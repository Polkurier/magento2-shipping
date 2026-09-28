<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\MoneyBox;

use Magento\Framework\App\RequestInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\WebsiteScope;

/**
 * Which website's Polkurier account the money box pages work with: the one asked for, or the
 * first website with credentials.
 */
class AccountScope
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly WebsiteScope $websiteScope,
        private readonly Config $config,
    ) {}

    public function getWebsiteId(): ?int
    {
        $requested = (int)$this->request->getParam('website');
        foreach ($this->websiteScope->getWebsiteIds() as $websiteId) {
            if (($requested === 0 || $requested === $websiteId)
                && $this->config->hasCredentials($this->websiteScope->getStoreId($websiteId))
            ) {
                return $websiteId;
            }
        }

        return null;
    }

    public function getStoreId(): ?int
    {
        $websiteId = $this->getWebsiteId();

        return $websiteId !== null ? $this->websiteScope->getStoreId($websiteId) : null;
    }
}
