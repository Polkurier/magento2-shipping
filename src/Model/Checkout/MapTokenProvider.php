<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Checkout;

use Magento\Framework\App\CacheInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Sdk\AccountCacheKey;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Shipping\Model\Sdk\RecentFailureException;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * The token the pickup point widget needs, shared by all customers of an account.
 *
 * Tokens live four hours and old ones keep working, so one is cached for a little less. Checkout
 * asks for it with every rate estimate, so a failed or empty answer is remembered for a moment
 * too, and the API isn't asked again with every keystroke of an address.
 */
class MapTokenProvider
{
    public const int CACHE_LIFETIME = 3 * 3600;
    public const int FAILURE_LIFETIME = 90;

    private const string FAILURE_SUFFIX = '_failed';

    /**
     * Stands for an empty token among the remembered failures.
     */
    private const string NO_TOKEN = '-';

    /**
     * Answers already given in this request, by cache key: the token, or the failure.
     *
     * @var array<string, string|PolkurierException>
     */
    private array $answers = [];

    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly CacheInterface $cache,
        private readonly ErrorMessages $errorMessages,
        private readonly AccountCacheKey $cacheKeys,
    ) {}

    /**
     * @return string empty when Polkurier gives no token
     * @throws PolkurierException
     */
    public function getToken(): string
    {
        $key = $this->cacheKeys->get('map_token');
        if (isset($this->answers[$key])) {
            return $this->answer($this->answers[$key]);
        }
        $token = $this->cache->load($key);
        if (\is_string($token) && $token !== '') {
            return $this->answers[$key] = $token;
        }
        $failure = $this->cache->load($key . self::FAILURE_SUFFIX);
        if (\is_string($failure) && $failure !== '') {
            return $this->answer($this->answers[$key] = $failure === self::NO_TOKEN ? '' : new RecentFailureException($failure));
        }

        try {
            $token = $this->clientPool->geo()->getMapToken()->getToken();
        } catch (PolkurierException $e) {
            $this->cache->save($this->errorMessages->describe($e), $key . self::FAILURE_SUFFIX, [CarrierCatalog::CACHE_TAG], self::FAILURE_LIFETIME);

            throw $this->answers[$key] = $e;
        }
        $this->cache->save(
            $token !== '' ? $token : self::NO_TOKEN,
            $token !== '' ? $key : $key . self::FAILURE_SUFFIX,
            [CarrierCatalog::CACHE_TAG],
            $token !== '' ? self::CACHE_LIFETIME : self::FAILURE_LIFETIME,
        );

        return $this->answers[$key] = $token;
    }

    /**
     * @throws PolkurierException
     */
    private function answer(string|PolkurierException $answer): string
    {
        if ($answer instanceof PolkurierException) {
            throw $answer;
        }

        return $answer;
    }
}
