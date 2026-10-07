<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Observer;

use Magento\Framework\Event\Observer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Enum\CacheTagEnum;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Sdk\PolkurierClient;
use Polkurier\Shipping\Observer\CredentialsChangedObserver;

#[CoversClass(CredentialsChangedObserver::class)]
class CredentialsChangedObserverTest extends TestCase
{
    public function testEverythingCachedForTheAccountGoesButThePoints(): void
    {
        self::assertNotContains(CacheTagEnum::Points, CacheTagEnum::ofAccount());
        $clients = $this->createMock(PolkurierClient::class);
        $clients->expects(self::once())->method('reset');
        $catalog = $this->createMock(CarrierCatalog::class);
        $catalog->expects(self::once())->method('clean');
        $cache = $this->createMock(PolkurierCache::class);
        $cache->expects(self::once())->method('cleanTags')->with(...CacheTagEnum::ofAccount())->willReturn(true);

        (new CredentialsChangedObserver($clients, $catalog, $cache))->execute(new Observer());
    }
}
