<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Polkurier\Shipping\Enum\CacheTagEnum;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Sdk\ClientPool;

/**
 * After the carrier configuration is saved, forgets the clients built from the previous
 * credentials and everything cached for the account (CacheTagEnum::ofAccount()).
 *
 * Event: admin_system_config_changed_section_carriers
 */
readonly class CredentialsChangedObserver implements ObserverInterface
{
    public function __construct(
        private ClientPool $clientPool,
        private CarrierCatalog $catalog,
        private PolkurierCache $cache,
    ) {}

    public function execute(Observer $observer): void
    {
        $this->clientPool->reset();
        $this->catalog->clean();
        $this->cache->cleanTags(...CacheTagEnum::ofAccount());
    }
}
