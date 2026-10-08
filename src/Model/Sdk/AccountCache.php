<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\Serialize\Serializer\Json;
use Polkurier\Shipping\Enum\Cache\CacheTagEnum;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Config\ConfigProvider;

/**
 * What is cached for the Polkurier account. Small answers are kept for a while, so that opening an
 * admin page doesn't wait for the API each time. The keys of everything else cached for the account
 * come from here too; they include the account, so another account's entries are never read.
 * Forgotten with the carrier list when the credentials change.
 */
readonly class AccountCache
{
    public function __construct(
        private PolkurierCache $polkurierCache,
        private Json $json,
        private ConfigProvider $configProvider,
    ) {}

    /**
     * The cached answer, or the one $load gives, cached for $lifetime seconds. A failing $load
     * caches nothing.
     *
     * @template T of scalar|array<mixed>|null
     *
     * @param callable(): T $load
     *
     * @return T
     */
    public function remember(string $name, int $lifetime, callable $load): mixed
    {
        $key = $this->key($name);
        $cached = $this->polkurierCache->load($key);
        if (\is_string($cached) && $cached !== '') {
            try {
                return $this->json->unserialize($cached)['value'] ?? null;
            } catch (\InvalidArgumentException) {
                $this->polkurierCache->remove($key);
            }
        }
        $value = $load();
        $this->polkurierCache->save(
            (string)$this->json->serialize(['value' => $value]),
            $key,
            [CacheTagEnum::Account->value],
            $lifetime,
        );

        return $value;
    }

    /**
     * The cache key of an entry for the current account.
     *
     * @param string $name what is kept, e.g. "carriers"
     * @param string ...$variant anything else the entry depends on, such as the SDK's version
     */
    public function key(string $name, string ...$variant): string
    {
        $account = $this->configProvider->getLogin() . '|' . (int)$this->configProvider->isSandbox();

        return 'polkurier_' . $name . '_' . \sha1(\implode('|', [$account, ...$variant]));
    }
}
