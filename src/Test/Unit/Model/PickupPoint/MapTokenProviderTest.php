<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\PickupPoint;

use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Exception\RecentFailureException;
use Polkurier\Shipping\Enum\Cache\CacheTagEnum;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;
use Polkurier\Shipping\Model\Sdk\AccountCache;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Test\Unit\Support\MemoryPolkurierCache;
use Polkurier\Shipping\Test\Unit\Support\SdkConfig;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Testing\RecordingClient;

#[CoversClass(MapTokenProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class MapTokenProviderTest extends SdkTestCase
{
    private const int NOW = 1790000000;

    private MemoryPolkurierCache $cache;

    protected function setUp(): void
    {
        $this->cache = new MemoryPolkurierCache();
    }

    public function testATokenIsAskedForOnceAndSharedFromTheCache(): void
    {
        $http = new RecordingClient(self::encodeSuccess(['token' => 'map-token']));

        $first = $this->createProvider($http)->getToken();
        $second = $this->createProvider($http)->getToken();

        self::assertSame(['map-token', 'map-token'], [$first, $second]);
        self::assertSame(1, $http->requestCount());
        self::assertSame([MapTokenProvider::CACHE_LIFETIME], \array_values($this->cache->lifetimes));
    }

    public function testAJwtSaysWhenItRunsOutItself(): void
    {
        $payload = \base64_encode((string)\json_encode(['iat' => self::NOW - 600, 'exp' => self::NOW + 9000]));
        $claims = \rtrim(\strtr($payload, '+/', '-_'), '=');
        $token = 'eyJ0eXAiOiJKV1QifQ.' . $claims . '.signature';
        $http = new RecordingClient(self::encodeSuccess(['token' => $token]));

        self::assertSame(self::NOW + 9000, $this->createProvider($http)->getExpiry());
        self::assertSame(
            ['token' => $token, 'expires' => self::NOW + 9000],
            $this->createProvider($http)->getTokenWithExpiry(),
            'The same from the cache.',
        );
        self::assertSame(1, $http->requestCount());
    }

    public function testATokenThatIsNoJwtSaysNothingOfWhenItRunsOut(): void
    {
        $http = new RecordingClient(self::encodeSuccess(['token' => 'map-token']));

        self::assertSame(0, $this->createProvider($http)->getExpiry());
    }

    public function testATokenThatCantBeHadIsAnEmptyOneAndLogged(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('Pickup point map token unavailable', self::arrayHasKey('exception'));

        self::assertSame(
            ['token' => '', 'expires' => 0],
            $this->createProvider(new RecordingClient('<html>Bad gateway</html>'), $logger)->getTokenWithExpiry(),
        );
    }

    public function testAFailureIsRememberedForAMoment(): void
    {
        $http = new RecordingClient('<html>Bad gateway</html>');
        try {
            $this->createProvider($http)->getToken();
            self::fail('The failure is reported.');
        } catch (\Polkurier\Webservice\Exception\Exception) {
        }

        $this->expectException(RecentFailureException::class);
        try {
            $this->createProvider($http)->getToken();
        } finally {
            self::assertSame(1, $http->requestCount(), 'The next request is told without asking again.');
            self::assertSame([PolkurierCache::FAILURE_LIFETIME], \array_values($this->cache->lifetimes));
        }
    }

    public function testAnEmptyTokenIsRememberedForAMoment(): void
    {
        $http = new RecordingClient(self::encodeSuccess(['token' => '']));

        $first = $this->createProvider($http)->getToken();
        $second = $this->createProvider($http)->getToken();

        self::assertSame(['', ''], [$first, $second]);
        self::assertSame(1, $http->requestCount());
        self::assertSame([PolkurierCache::FAILURE_LIFETIME], \array_values($this->cache->lifetimes));
        self::assertSame(
            [[CacheTagEnum::MapToken->value, CacheTagEnum::Failures->value]],
            \array_values($this->cache->tags),
            'Forgotten with the other failures when the credentials change.',
        );
    }

    private function createProvider(RecordingClient $http, ?Logger $logger = null): MapTokenProvider
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getLogin')->willReturn(SdkConfig::LOGIN);

        return new MapTokenProvider(
            $this->createClientPool($http),
            $this->cache,
            new ErrorMessageResolver(),
            new AccountCache($this->createStub(PolkurierCache::class), new Json(), $config),
            $logger ?? $this->createStub(Logger::class),
        );
    }
}
