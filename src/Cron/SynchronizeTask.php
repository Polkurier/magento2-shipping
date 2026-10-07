<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Cron;

use Polkurier\Shipping\Exception\SynchronizationFailedException;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sync\Synchronization;
use Psr\Log\LoggerInterface;

/**
 * Hourly synchronization of the address book, bank accounts and pack templates, once the
 * Polkurier credentials are set. The carriers have a daily job of their own (RefreshCarriersTask).
 */
readonly class SynchronizeTask
{
    public function __construct(
        private Synchronization $synchronization,
        private ConfigProvider $configProvider,
        private LoggerInterface $logger,
    ) {}

    public function execute(): void
    {
        if (!$this->configProvider->isSyncEnabled() || !$this->configProvider->hasCredentials()) {
            return;
        }
        // Logged rather than thrown: the next run tries again.
        try {
            foreach ($this->synchronization->synchronize() as $line) {
                $this->logger->info('Polkurier synchronization: ' . $line);
            }
        } catch (SynchronizationFailedException) {
            // Each entity's failure is logged already.
        } catch (\Throwable $e) {
            $this->logger->warning('Polkurier synchronization failed', ['exception' => $e]);
        }
    }
}
