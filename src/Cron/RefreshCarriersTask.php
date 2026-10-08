<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Cron;

use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigSynchronizer;
use Polkurier\Shipping\Model\Config\ConfigProvider;

/**
 * Daily refresh of the account's carriers; the admin is told about carriers gained or lost. They
 * change rarely, so once a day is enough; the Synchronize with Polkurier button refreshes them at
 * once.
 */
readonly class RefreshCarriersTask
{
    public function __construct(
        private CarrierConfigSynchronizer $carrierConfigSynchronizer,
        private ConfigProvider $configProvider,
        private Logger $logger,
    ) {}

    public function execute(): void
    {
        // Like every job that calls Polkurier: only when switched on and the account is configured.
        if (!$this->configProvider->isSyncEnabled() || !$this->configProvider->hasCredentials()) {
            return;
        }
        // Logged rather than thrown: the next run tries again.
        try {
            $this->carrierConfigSynchronizer->synchronize();
        } catch (\Throwable $e) {
            $this->logger->warning('Polkurier carrier list refresh failed', ['exception' => $e]);
        }
    }
}
