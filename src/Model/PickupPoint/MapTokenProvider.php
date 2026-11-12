<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\PickupPoint;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Enum\Cache\CacheTagEnum;
use Polkurier\Shipping\Exception\RecentFailureException;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Sdk\AccountCache;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * The token the pickup point widget needs, shared by all customers of an account.
 *
 * Tokens live four hours and old ones keep working, so one is cached for a little less: a page that
 * keeps one asks for a fresh one before it runs out (getExpiry()).
 * Checkout asks for it with every rate estimate, so a failed or empty answer is remembered for a
 * moment too, and the API isn't asked again with every keystroke of an address.
 */
class MapTokenProvider implements ResetAfterRequestInterface
{
    public const int CACHE_LIFETIME = 3 * 3600;

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
        private readonly PolkurierClientInterface $polkurierClient,
        private readonly PolkurierCache $polkurierCache,
        private readonly ErrorMessageResolver $errorMessageResolver,
        private readonly AccountCache $accountCache,
        private readonly Logger $logger,
    ) {}

    /**
     * @return string empty when Polkurier gives no token
     * @throws PolkurierException
     */
    public function getToken(): string
    {
        $key = $this->accountCache->key('map_token');
        if (isset($this->answers[$key])) {
            return $this->answer($this->answers[$key]);
        }
        $cached = $this->polkurierCache->load($key);
        if (\is_string($cached) && $cached !== '') {
            return $this->answers[$key] = $cached;
        }
        $failure = $this->polkurierCache->load($key . self::FAILURE_SUFFIX);
        if (\is_string($failure) && $failure !== '') {
            return $this->answer(
                $this->answers[$key] = $failure === self::NO_TOKEN ? '' : new RecentFailureException($failure),
            );
        }

        try {
            $token = $this->polkurierClient->geo()->getMapToken()->getToken();
        } catch (PolkurierException $e) {
            $this->polkurierCache->save(
                (string)$this->errorMessageResolver->phrase($e),
                $key . self::FAILURE_SUFFIX,
                [CacheTagEnum::MapToken->value, CacheTagEnum::Failures->value],
                PolkurierCache::FAILURE_LIFETIME,
            );

            throw $this->answers[$key] = $e;
        }
        $this->polkurierCache->save(
            $token !== '' ? $token : self::NO_TOKEN,
            $token !== '' ? $key : $key . self::FAILURE_SUFFIX,
            [CacheTagEnum::MapToken->value],
            $token !== '' ? self::CACHE_LIFETIME : PolkurierCache::FAILURE_LIFETIME,
        );

        return $this->answers[$key] = $token;
    }

    /**
     * When the token from getToken() stops working, as a Unix timestamp: the expiry of that JWT, or
     * 0 when there's no token.
     *
     * @throws PolkurierException
     */
    public function getExpiry(): int
    {
        $parts = \explode('.', $this->getToken());
        if (\count($parts) === 3) {
            $claims = \json_decode((string)\base64_decode(\strtr($parts[1], '-_', '+/'), true), true);
            if (\is_array($claims) && \is_numeric($claims['exp'] ?? null)) {
                return (int)$claims['exp'];
            }
        }

        return 0;
    }

    /**
     * The token and when it runs out, for a page to open the map with; empty and 0 when it can't be
     * obtained, which is logged.
     *
     * @return array{token: string, expires: int}
     */
    public function getTokenWithExpiry(): array
    {
        try {
            return ['token' => $this->getToken(), 'expires' => $this->getExpiry()];
        } catch (PolkurierException $e) {
            $this->logger->warning('Pickup point map token unavailable', ['exception' => $e]);

            return ['token' => '', 'expires' => 0];
        }
    }

    public function _resetState(): void
    {
        $this->answers = [];
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
