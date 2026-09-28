<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Checkout;

use Magento\Framework\App\CacheInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * The token the pickup point widget needs, shared by all customers of an account.
 *
 * Tokens live four hours and old ones keep working, so one is cached for a little less.
 */
class MapTokenProvider
{
    public const int CACHE_LIFETIME = 3 * 3600;

    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly Config $config,
        private readonly CacheInterface $cache,
    ) {}

    /**
     * @throws PolkurierException
     */
    public function getToken(?int $storeId = null): string
    {
        $key = 'polkurier_map_token_' . \sha1($this->config->getLogin($storeId) . '|' . (int)$this->config->isSandbox($storeId));
        $token = $this->cache->load($key);
        if (\is_string($token) && $token !== '') {
            return $token;
        }

        $token = $this->clientPool->geo($storeId)->getMapToken()->getToken();
        if ($token !== '') {
            $this->cache->save($token, $key, [\Polkurier\Shipping\Model\Carrier\CarrierCatalog::CACHE_TAG], self::CACHE_LIFETIME);
        }

        return $token;
    }
}
