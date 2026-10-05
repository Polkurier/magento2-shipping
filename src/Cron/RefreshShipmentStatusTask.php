<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Cron;

use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Shipment\StatusRefresher;

readonly class RefreshShipmentStatusTask
{
    public function __construct(
        private StatusRefresher $statusRefresher,
        private Config $config,
    ) {}

    public function execute(): void
    {
        // As every job asking Polkurier: not before the account is configured.
        if (!$this->config->hasCredentials()) {
            return;
        }
        $this->statusRefresher->refreshPending();
    }
}
