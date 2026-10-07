<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Cron;

use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;

/**
 * Updates the statuses of the shipments not yet delivered, as often as configured.
 */
readonly class RefreshShipmentStatusTask
{
    public function __construct(
        private StatusRefreshService $statusRefreshService,
        private ConfigProvider $configProvider,
    ) {}

    public function execute(): void
    {
        // As every job asking Polkurier: switched on, and not before the account is configured.
        if (!$this->configProvider->isStatusSyncEnabled() || !$this->configProvider->hasCredentials()) {
            return;
        }
        $this->statusRefreshService->refreshPending();
    }
}
