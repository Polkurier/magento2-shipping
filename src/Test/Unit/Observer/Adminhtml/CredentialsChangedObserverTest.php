<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Observer\Adminhtml;

use Magento\Framework\Event\Observer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Enum\Cache\CacheTagEnum;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Sdk\PolkurierClient;
use Polkurier\Shipping\Observer\Adminhtml\CredentialsChangedObserver;

#[CoversClass(CredentialsChangedObserver::class)]
class CredentialsChangedObserverTest extends TestCase
{
    public function testEverythingCachedForTheAccountGoesButThePoints(): void
    {
        self::assertNotContains(CacheTagEnum::Points, CacheTagEnum::getAccountTags());
        $clients = $this->createMock(PolkurierClient::class);
        $clients->expects(self::once())->method('reset');
        $catalog = $this->createMock(CarrierCatalog::class);
        $catalog->expects(self::once())->method('clean');
        $cache = $this->createMock(PolkurierCache::class);
        $cache->expects(self::once())->method('cleanTags')->with(...CacheTagEnum::getAccountTags())->willReturn(true);

        (new CredentialsChangedObserver($clients, $catalog, $cache))->execute(new Observer());
    }
}
