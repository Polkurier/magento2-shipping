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
class Synchronize
{
    public function __construct(
        private readonly SynchronizationInterface $synchronization,
        private readonly CarrierConfigSynchronizer $carrierConfigSynchronizer,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(): void
    {
        if (!$this->config->isSyncEnabled() || !$this->config->hasCredentials()) {
            return;
        }
        try {
            $this->carrierConfigSynchronizer->synchronize();
            foreach ($this->synchronization->synchronize() as $line) {
                $this->logger->info('Polkurier synchronization: ' . $line);
            }
        } catch (\Throwable $e) {
            // Logged rather than thrown: the next run tries again.
            $this->logger->warning('Polkurier synchronization failed', ['error' => $e->getMessage()]);
        }
    }
}
