<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Checkout;

use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Model\Checkout\MapTokenProvider;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Sdk\AccountCacheKey;
use Polkurier\Shipping\Model\Sdk\RecentFailureException;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Testing\RecordingClient;

#[CoversClass(MapTokenProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class MapTokenProviderTest extends SdkTestCase
{
    private const int NOW = 1790000000;

    /**
     * @var array<string, string>
     */
    private array $cached = [];

    /**
     * @var array<string, int>
     */
    private array $lifetimes = [];

    public function testATokenIsAskedForOnceAndSharedFromTheCache(): void
    {
        $http = new RecordingClient(self::success(['token' => 'map-token']));

        $first = $this->provider($http)->getToken();
        $second = $this->provider($http)->getToken();

        self::assertSame(['map-token', 'map-token'], [$first, $second]);
        self::assertSame(1, $http->requestCount());
        self::assertSame([MapTokenProvider::CACHE_LIFETIME], \array_values($this->lifetimes));
    }

    public function testATokenRunsOutFourHoursAfterItWasIssuedEvenFromTheCache(): void
    {
        $http = new RecordingClient(self::success(['token' => 'map-token']));

        $issued = $this->provider($http)->getExpiry();
        $cached = $this->provider($http)->getExpiry();

        self::assertSame([self::NOW + MapTokenProvider::TOKEN_LIFETIME, self::NOW + MapTokenProvider::TOKEN_LIFETIME], [$issued, $cached]);
        self::assertSame(1, $http->requestCount());
    }

    public function testAJwtSaysWhenItRunsOutItself(): void
    {
        $claims = \rtrim(\strtr(\base64_encode((string)\json_encode(['iat' => self::NOW - 600, 'exp' => self::NOW + 9000])), '+/', '-_'), '=');
        $http = new RecordingClient(self::success(['token' => 'eyJ0eXAiOiJKV1QifQ.' . $claims . '.signature']));

        self::assertSame(self::NOW + 9000, $this->provider($http)->getExpiry());
    }

    public function testATokenCachedWithoutItsTimeIsTakenAsOldAsCanBe(): void
    {
        $http = new RecordingClient(self::success(['token' => 'map-token']));
        $this->provider($http)->getToken();
        $key = \array_key_first($this->cached);
        $this->cached[$key] = 'old-token';

        self::assertSame('old-token', $this->provider($http)->getToken());
        self::assertSame(self::NOW - MapTokenProvider::CACHE_LIFETIME + MapTokenProvider::TOKEN_LIFETIME, $this->provider($http)->getExpiry());
    }

    public function testAFailureIsRememberedForAMoment(): void
    {
        $http = new RecordingClient('<html>Bad gateway</html>');
        try {
            $this->provider($http)->getToken();
            self::fail('The failure is reported.');
        } catch (\Polkurier\Webservice\Exception\Exception) {
        }

        $this->expectException(RecentFailureException::class);
        try {
            $this->provider($http)->getToken();
        } finally {
            self::assertSame(1, $http->requestCount(), 'The next request is told without asking again.');
            self::assertSame([MapTokenProvider::FAILURE_LIFETIME], \array_values($this->lifetimes));
        }
    }

    public function testAnEmptyTokenIsRememberedForAMoment(): void
    {
        $http = new RecordingClient(self::success(['token' => '']));

        $first = $this->provider($http)->getToken();
        $second = $this->provider($http)->getToken();

        self::assertSame(['', ''], [$first, $second]);
        self::assertSame(1, $http->requestCount());
        self::assertSame([MapTokenProvider::FAILURE_LIFETIME], \array_values($this->lifetimes));
    }

    private function provider(RecordingClient $http): MapTokenProvider
    {
        $config = $this->createStub(Config::class);
        $config->method('getLogin')->willReturn(self::LOGIN);
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn(string $key): string|false => $this->cached[$key] ?? false);
        $cache->method('save')->willReturnCallback(function (string $data, string $key, array $tags, int $lifetime): bool {
            $this->cached[$key] = $data;
            $this->lifetimes[$key] = $lifetime;

            return true;
        });

        return new MapTokenProvider($this->clientPool($http), $cache, new ErrorMessages(), new AccountCacheKey($config), new FixedDateTime(self::NOW));
    }
}
