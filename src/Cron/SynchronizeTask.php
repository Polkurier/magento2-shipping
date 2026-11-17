<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Cron;

use Polkurier\Shipping\Exception\SynchronizationFailedException;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sync\SettingsSynchronizer;

/**
 * Hourly synchronization of the address book, bank accounts and pack templates, once the
 * Polkurier credentials are set. The carriers have a daily job of their own
 * ({@see \Polkurier\Shipping\Cron\RefreshCarriersTask}).
 */
readonly class SynchronizeTask
{
    public function __construct(
        private SettingsSynchronizer $settingsSynchronizer,
        private ConfigProvider $configProvider,
        private Logger $logger,
    ) {}

    public function execute(): void
    {
        if (!$this->configProvider->isSyncEnabled() || !$this->configProvider->hasCredentials()) {
            return;
        }
        // Logged rather than thrown: the next run tries again.
        try {
            foreach ($this->settingsSynchronizer->synchronize() as $line) {
                $this->logger->info('Polkurier synchronization: ' . $line);
            }
        } catch (SynchronizationFailedException) {
            // Each entity's failure is logged already.
        } catch (\Throwable $e) {
            $this->logger->warning('Polkurier synchronization failed', ['exception' => $e]);
        }
    }
}
