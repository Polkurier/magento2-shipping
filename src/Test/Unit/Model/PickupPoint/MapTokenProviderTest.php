<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\PickupPoint;

use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Exception\RecentFailureException;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;
use Polkurier\Shipping\Model\Sdk\AccountCache;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
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

    public function testAJwtSaysWhenItRunsOutItself(): void
    {
        $payload = \base64_encode((string)\json_encode(['iat' => self::NOW - 600, 'exp' => self::NOW + 9000]));
        $claims = \rtrim(\strtr($payload, '+/', '-_'), '=');
        $token = 'eyJ0eXAiOiJKV1QifQ.' . $claims . '.signature';
        $http = new RecordingClient(self::success(['token' => $token]));

        self::assertSame(self::NOW + 9000, $this->provider($http)->getExpiry());
        self::assertSame(
            ['token' => $token, 'expires' => self::NOW + 9000],
            $this->provider($http)->getTokenWithExpiry(),
            'The same from the cache.',
        );
        self::assertSame(1, $http->requestCount());
    }

    public function testATokenThatIsNoJwtSaysNothingOfWhenItRunsOut(): void
    {
        $http = new RecordingClient(self::success(['token' => 'map-token']));

        self::assertSame(0, $this->provider($http)->getExpiry());
    }

    public function testATokenThatCantBeHadIsAnEmptyOneAndLogged(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('Pickup point map token unavailable', self::arrayHasKey('exception'));

        self::assertSame(
            ['token' => '', 'expires' => 0],
            $this->provider(new RecordingClient('<html>Bad gateway</html>'), $logger)->getTokenWithExpiry(),
        );
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

    private function provider(RecordingClient $http, ?Logger $logger = null): MapTokenProvider
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getLogin')->willReturn(self::LOGIN);
        $cache = $this->createStub(PolkurierCache::class);
        $cache->method('load')->willReturnCallback(fn(string $key): string|false => $this->cached[$key] ?? false);
        $cache->method('save')
            ->willReturnCallback(function (string $data, string $key, array $tags, int $lifetime): bool {
                $this->cached[$key] = $data;
                $this->lifetimes[$key] = $lifetime;

                return true;
            });

        return new MapTokenProvider(
            $this->clientPool($http),
            $cache,
            new ErrorMessageResolver(),
            new AccountCache($this->createStub(PolkurierCache::class), new Json(), $config),
            $logger ?? $this->createStub(Logger::class),
        );
    }
}
