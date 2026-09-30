<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Sdk\AccountCache;
use Polkurier\Shipping\Model\Sdk\AccountCacheKey;

#[CoversClass(AccountCache::class)]
#[AllowMockObjectsWithoutExpectations]
class AccountCacheTest extends TestCase
{
    /**
     * @var array<string, array{0: string, 1: string[]}>
     */
    private array $cached = [];

    private string $login = '12345';

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
        self::assertSame([[CarrierCatalog::CACHE_TAG]], \array_values(\array_unique(\array_column($this->cached, 1), \SORT_REGULAR)));
    }

    public function testAFailureIsNotCached(): void
    {
        try {
            $this->cache()->remember('pin_locked', 900, static fn(): bool => throw new \RuntimeException('Down.'));
        } catch (\RuntimeException) {
        }

        self::assertSame([], $this->cached);
    }

    private function cache(): AccountCache
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn(string $key): string|false => $this->cached[$key][0] ?? false);
        $cache->method('save')->willReturnCallback(function (string $data, string $key, array $tags): bool {
            $this->cached[$key] = [$data, $tags];

            return true;
        });
        $config = $this->createStub(Config::class);
        $config->method('getLogin')->willReturnCallback(fn(): string => $this->login);

        return new AccountCache($cache, new Json(), new AccountCacheKey($config));
    }
}
