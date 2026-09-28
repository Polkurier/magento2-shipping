<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\SyncEntityEnum;
use Polkurier\Shipping\Api\SynchronizationInterface;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\WebsiteScope;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Runs the sync of every kind of entry, or of the kinds asked for, for one website.
 */
class Synchronization implements SynchronizationInterface
{
    /**
     * @param AdapterInterface[] $adapters one per kind, in the order they run
     */
    public function __construct(
        private readonly SyncEngine $engine,
        private readonly WebsiteScope $websiteScope,
        private readonly ErrorMessages $errorMessages,
        private readonly array $adapters = [],
    ) {}

    /**
     * @param SyncEntityEnum[] $entities
     * @return string[]
     */
    public function synchronize(int $websiteId, array $entities = []): array
    {
        $storeId = $this->websiteScope->getStoreId($websiteId);
        $summary = [];
        foreach ($this->adapters as $adapter) {
            $kind = $adapter->getEntityType();
            if ($entities !== [] && !\in_array($kind, $entities, true)) {
                continue;
            }
            try {
                $summary[] = $this->engine->run($adapter, $websiteId, $storeId)->summary();
            } catch (PolkurierException $e) {
                throw new LocalizedException(\__('%1: %2', $kind->label(), $this->errorMessages->describe($e)), $e);
            }
        }

        return $summary;
    }
}
