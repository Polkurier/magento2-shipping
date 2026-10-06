<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Checkout;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Enum\CacheTagEnum;
use Polkurier\Shipping\Exception\RecentFailureException;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Sdk\AccountCacheKey;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * The token the pickup point widget needs, shared by all customers of an account.
 *
 * Tokens live four hours and old ones keep working, so one is cached for a little less, with when
 * it was issued: a page that keeps one asks for a fresh one before it runs out (getExpiry()).
 * Checkout asks for it with every rate estimate, so a failed or empty answer is remembered for a
 * moment too, and the API isn't asked again with every keystroke of an address.
 */
class MapTokenProvider
{
    public const int CACHE_LIFETIME = 3 * 3600;
    public const int TOKEN_LIFETIME = 4 * 3600;
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

    /**
     * When each token given in this request was issued, by cache key.
     *
     * @var array<string, int>
     */
    private array $issuedAt = [];

    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly PolkurierCache $cache,
        private readonly ErrorMessages $errorMessages,
        private readonly AccountCacheKey $cacheKeys,
        private readonly DateTime $dateTime,
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
        $cached = $this->cache->load($key);
        if (\is_string($cached) && $cached !== '') {
            [$issuedAt, $token] = \str_contains($cached, '|') ? \explode('|', $cached, 2) : [0, $cached];
            // A token cached without its time is taken as old as a cached one can be.
            $this->issuedAt[$key] = (int)$issuedAt ?: $this->dateTime->gmtTimestamp() - self::CACHE_LIFETIME;

            return $this->answers[$key] = $token;
        }
        $failure = $this->cache->load($key . self::FAILURE_SUFFIX);
        if (\is_string($failure) && $failure !== '') {
            return $this->answer($this->answers[$key] = $failure === self::NO_TOKEN ? '' : new RecentFailureException($failure));
        }

        try {
            $token = $this->clientPool->geo()->getMapToken()->getToken();
        } catch (PolkurierException $e) {
            $this->cache->save($this->errorMessages->describe($e), $key . self::FAILURE_SUFFIX, [CacheTagEnum::MapToken->value, CacheTagEnum::Failures->value], self::FAILURE_LIFETIME);

            throw $this->answers[$key] = $e;
        }
        $this->issuedAt[$key] = $this->dateTime->gmtTimestamp();
        $this->cache->save(
            $token !== '' ? $this->issuedAt[$key] . '|' . $token : self::NO_TOKEN,
            $token !== '' ? $key : $key . self::FAILURE_SUFFIX,
            [CacheTagEnum::MapToken->value],
            $token !== '' ? self::CACHE_LIFETIME : self::FAILURE_LIFETIME,
        );

        return $this->answers[$key] = $token;
    }

    /**
     * When the token getToken() gives stops working, as a Unix timestamp; 0 when there's none.
     * The token says so itself when it's a JWT with an expiry; otherwise it's taken to live
     * TOKEN_LIFETIME from when it was issued.
     *
     * @throws PolkurierException
     */
    public function getExpiry(): int
    {
        $token = $this->getToken();
        if ($token === '') {
            return 0;
        }
        $parts = \explode('.', $token);
        if (\count($parts) === 3) {
            $claims = \json_decode((string)\base64_decode(\strtr($parts[1], '-_', '+/'), true), true);
            if (\is_array($claims) && \is_numeric($claims['exp'] ?? null)) {
                return (int)$claims['exp'];
            }
        }
        $key = $this->cacheKeys->get('map_token');

        return isset($this->issuedAt[$key]) ? $this->issuedAt[$key] + self::TOKEN_LIFETIME : 0;
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
