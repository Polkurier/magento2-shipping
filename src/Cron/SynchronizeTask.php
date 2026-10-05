<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Cron;

use Polkurier\Shipping\Api\SynchronizationInterface;
use Polkurier\Shipping\Model\Carrier\CarrierConfigSynchronizer;
use Polkurier\Shipping\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Hourly synchronization of the address book, bank accounts, pack templates and the carrier list,
 * once the Polkurier credentials are set.
 */
readonly class SynchronizeTask
{
    public function __construct(
        private SynchronizationInterface $synchronization,
        private CarrierConfigSynchronizer $carrierConfigSynchronizer,
        private Config $config,
        private LoggerInterface $logger,
    ) {}

    public function execute(): void
    {
        if (!$this->config->isSyncEnabled() || !$this->config->hasCredentials()) {
            return;
        }
        // Each on its own, and logged rather than thrown: the next run tries again.
        try {
            $this->carrierConfigSynchronizer->synchronize();
        } catch (\Throwable $e) {
            $this->logger->warning('Polkurier carrier list refresh failed', ['error' => $e->getMessage()]);
        }
        try {
            foreach ($this->synchronization->synchronize() as $line) {
                $this->logger->info('Polkurier synchronization: ' . $line);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Polkurier synchronization failed', ['error' => $e->getMessage()]);
        }
    }
}
