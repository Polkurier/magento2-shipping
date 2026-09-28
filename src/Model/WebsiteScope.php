<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Store\Model\StoreManagerInterface;

/**
 * Credentials are configured per website; the SDK is resolved per store. This picks the store
 * whose configuration stands for a website: its default store view.
 */
class WebsiteScope
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
    ) {}

    public function getStoreId(int $websiteId): int
    {
        $groupId = (int)$this->storeManager->getWebsite($websiteId)->getDefaultGroupId();

        return $groupId ? (int)$this->storeManager->getGroup((string)$groupId)->getDefaultStoreId() : 0;
    }

    public function getWebsiteId(int $storeId): int
    {
        return (int)$this->storeManager->getStore($storeId)->getWebsiteId();
    }

    /**
     * @return int[] ids of all websites, admin excluded
     */
    public function getWebsiteIds(): array
    {
        return \array_values(\array_map(
            static fn($website): int => (int)$website->getId(),
            $this->storeManager->getWebsites(),
        ));
    }
}
