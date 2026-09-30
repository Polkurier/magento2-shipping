<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Sdk\AccountCacheKey;

#[CoversClass(AccountCacheKey::class)]
#[AllowMockObjectsWithoutExpectations]
class AccountCacheKeyTest extends TestCase
{
    private string $login = '12345';
    private bool $sandbox = false;

    public function testTheKeyFollowsTheAccountAndWhatElseTheEntryDependsOn(): void
    {
        $keys = $this->keys();

        // The shape the caches used before, so their entries stay valid.
        self::assertSame('polkurier_map_token_' . \sha1('12345|0'), $keys->get('map_token'));
        self::assertSame('polkurier_carriers_' . \sha1('12345|0|2.0.0@abc'), $keys->get('carriers', '2.0.0@abc'));
    }

    public function testAnotherAccountOrTheSandboxNeverSeesTheEntry(): void
    {
        $production = $this->keys()->get('map_token');
        $this->sandbox = true;
        $sandbox = $this->keys()->get('map_token');
        $this->login = '67890';
        $other = $this->keys()->get('map_token');

        self::assertCount(3, \array_unique([$production, $sandbox, $other]));
    }

    private function keys(): AccountCacheKey
    {
        $config = $this->createStub(Config::class);
        $config->method('getLogin')->willReturnCallback(fn(): string => $this->login);
        $config->method('isSandbox')->willReturnCallback(fn(): bool => $this->sandbox);

        return new AccountCacheKey($config);
    }
}
