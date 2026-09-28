<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Sdk\ClientPool;

/**
 * After the carrier configuration is saved, forgets clients and carrier lists built from the
 * previous credentials.
 *
 * Event: admin_system_config_changed_section_carriers
 */
class CredentialsChanged implements ObserverInterface
{
    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly CarrierCatalog $catalog,
    ) {}

    public function execute(Observer $observer): void
    {
        $this->clientPool->reset();
        $this->catalog->clean();
    }
}
