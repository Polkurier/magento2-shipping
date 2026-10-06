<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Cron;

use Polkurier\Shipping\Model\Carrier\CarrierConfigSynchronizer;
use Polkurier\Shipping\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Daily refresh of the account's carriers: those it gained or lost are told about. They change
 * rarely, so once a day is enough; the Synchronize with Polkurier button refreshes them at once.
 */
readonly class RefreshCarriersTask
{
    public function __construct(
        private CarrierConfigSynchronizer $carrierConfigSynchronizer,
        private Config $config,
        private LoggerInterface $logger,
    ) {}

    public function execute(): void
    {
        // As every job asking Polkurier: switched on, and not before the account is configured.
        if (!$this->config->isSyncEnabled() || !$this->config->hasCredentials()) {
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
