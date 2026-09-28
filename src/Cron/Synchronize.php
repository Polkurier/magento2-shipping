<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Cron;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\SynchronizationInterface;
use Polkurier\Shipping\Model\Carrier\CarrierConfigSynchronizer;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\WebsiteScope;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Psr\Log\LoggerInterface;

/**
 * Hourly synchronization of the address book, bank accounts, pack templates and the carrier list,
 * for every website with Polkurier credentials. Each website is resolved with its own client.
 */
class Synchronize
{
    public function __construct(
        private readonly SynchronizationInterface $synchronization,
        private readonly CarrierConfigSynchronizer $carrierConfigSynchronizer,
        private readonly WebsiteScope $websiteScope,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(): void
    {
        foreach ($this->websiteScope->getWebsiteIds() as $websiteId) {
            $storeId = $this->websiteScope->getStoreId($websiteId);
            if (!$this->config->isSyncEnabled($storeId) || !$this->config->hasCredentials($storeId)) {
                continue;
            }
            try {
                $this->carrierConfigSynchronizer->synchronize($websiteId);
                foreach ($this->synchronization->synchronize($websiteId) as $line) {
                    $this->logger->info('Polkurier synchronization: ' . $line, ['website' => $websiteId]);
                }
            } catch (LocalizedException | PolkurierException $e) {
                $this->logger->warning('Polkurier synchronization failed', ['website' => $websiteId, 'error' => $e->getMessage()]);
            }
        }
    }
}
