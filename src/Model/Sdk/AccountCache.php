<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;

/**
 * Small answers about the Polkurier account kept for a while, so that opening an admin page
 * doesn't wait for the API each time. Forgotten with the carrier list when the credentials change.
 */
class AccountCache
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly AccountCacheKey $cacheKeys,
    ) {}

    /**
     * The cached answer, or the one $load gives, cached for $lifetime seconds. A failing $load
     * caches nothing.
     *
     * @template T of scalar|array<mixed>|null
     * @param callable(): T $load
     * @return T
     */
    public function remember(string $name, int $lifetime, callable $load): mixed
    {
        $key = $this->cacheKeys->get($name);
        $cached = $this->cache->load($key);
        if (\is_string($cached) && $cached !== '') {
            try {
                return $this->json->unserialize($cached)['value'] ?? null;
            } catch (\InvalidArgumentException) {
                $this->cache->remove($key);
            }
        }
        $value = $load();
        $this->cache->save((string)$this->json->serialize(['value' => $value]), $key, [CarrierCatalog::CACHE_TAG], $lifetime);

        return $value;
    }
}
