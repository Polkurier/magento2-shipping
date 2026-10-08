<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Enum\Cache\CacheTagEnum;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sdk\AccountCache;

#[CoversClass(AccountCache::class)]
#[AllowMockObjectsWithoutExpectations]
class AccountCacheTest extends TestCase
{
    /**
     * @var array<string, array{0: string, 1: string[]}>
     */
    private array $cached = [];

    private string $login = '12345';
    private bool $sandbox = false;

    public function testTheAnswerIsAskedForOncePerAccount(): void
    {
        $asked = 0;
        $load = static function () use (&$asked): bool {
            $asked++;

            return false;
        };

        self::assertFalse($this->cache()->remember('pin_locked', 900, $load));
        self::assertFalse($this->cache()->remember('pin_locked', 900, $load), 'A false answer is cached too.');
        $this->login = '777';
        $this->cache()->remember('pin_locked', 900, $load);

        self::assertSame(2, $asked, 'Other credentials are another account.');
        self::assertSame(
            [[CacheTagEnum::Account->value]],
            \array_values(\array_unique(\array_column($this->cached, 1), \SORT_REGULAR)),
        );
    }

    public function testAFailureIsNotCached(): void
    {
        try {
            $this->cache()->remember('pin_locked', 900, static fn(): bool => throw new \RuntimeException('Down.'));
        } catch (\RuntimeException) {
        }

        self::assertSame([], $this->cached);
    }

    public function testTheKeyFollowsTheAccountAndWhatElseTheEntryDependsOn(): void
    {
        $keys = $this->cache();

        // The cache's prefix, then a hash of the login, the sandbox flag and what else the entry depends on.
        self::assertSame('polkurier_map_token_' . \sha1('12345|0'), $keys->key('map_token'));
        self::assertSame('polkurier_carriers_' . \sha1('12345|0|2.0.0@abc'), $keys->key('carriers', '2.0.0@abc'));
    }

    public function testAnotherAccountOrTheSandboxNeverSeesTheEntry(): void
    {
        $production = $this->cache()->key('map_token');
        $this->sandbox = true;
        $sandbox = $this->cache()->key('map_token');
        $this->login = '67890';
        $other = $this->cache()->key('map_token');

        self::assertCount(3, \array_unique([$production, $sandbox, $other]));
    }

    private function cache(): AccountCache
    {
        $cache = $this->createStub(PolkurierCache::class);
        $cache->method('load')->willReturnCallback(fn(string $key): string|false => $this->cached[$key][0] ?? false);
        $cache->method('save')->willReturnCallback(function (string $data, string $key, array $tags): bool {
            $this->cached[$key] = [$data, $tags];

            return true;
        });
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getLogin')->willReturnCallback(fn(): string => $this->login);
        $config->method('isSandbox')->willReturnCallback(fn(): bool => $this->sandbox);

        return new AccountCache($cache, new Json(), $config);
    }
}
