<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Polkurier\Shipping\Enum\Sync\SyncEntityEnum;
use Polkurier\Shipping\Exception\SynchronizationFailedException;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Address\AddressSyncAdapter;
use Polkurier\Shipping\Model\BankAccount\BankAccountSyncAdapter;
use Polkurier\Shipping\Model\PackTemplate\PackTemplateSyncAdapter;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;

/**
 * Brings the address book, bank accounts and pack templates kept in Magento level with the
 * Polkurier account; the later change wins. Runs every kind of entry, or the kinds asked for. A
 * kind that can't be synchronized doesn't stop the others: its failure is logged and reported with
 * their summaries. It's an error only when every kind asked for failed.
 */
readonly class Synchronization
{
    /**
     * @var AdapterInterface[] one per kind, in the order they run
     */
    private array $adapters;

    public function __construct(
        private SyncEngine $syncEngine,
        private ErrorMessageResolver $errorMessageResolver,
        private SyncLock $syncLock,
        private Logger $logger,
        AddressSyncAdapter $addressSyncAdapter,
        BankAccountSyncAdapter $bankAccountSyncAdapter,
        PackTemplateSyncAdapter $packTemplateSyncAdapter,
    ) {
        $this->adapters = [$addressSyncAdapter, $bankAccountSyncAdapter, $packTemplateSyncAdapter];
    }

    /**
     * @param SyncEntityEnum[] $entities the kinds to synchronize; empty means all
     *
     * @return string[] a human-readable summary line per kind
     */
    public function synchronize(array $entities = []): array
    {
        return $this->syncLock->run(fn(): array => $this->run($entities));
    }

    /**
     * @param SyncEntityEnum[] $entities
     *
     * @return string[]
     */
    private function run(array $entities): array
    {
        $summary = [];
        $failures = [];
        $lastFailure = null;
        foreach ($this->adapters as $adapter) {
            $kind = $adapter->getEntityType();
            if ($entities !== [] && !\in_array($kind, $entities, true)) {
                continue;
            }
            try {
                $summary[] = $this->syncEngine->run($adapter)->summary();
            } catch (\Exception $e) {
                $line = (string)__('%1: %2', $kind->label(), $this->errorMessageResolver->phrase($e));
                $this->logger->warning(
                    'Polkurier synchronization failed',
                    ['entity' => $kind->value, 'exception' => $e],
                );
                $failures[] = $line;
                $lastFailure = $e;
            }
        }
        if ($summary === [] && $failures !== []) {
            throw new SynchronizationFailedException(
                __('Synchronization with Polkurier failed. %1', \implode(' ', $failures)),
                $lastFailure,
            );
        }

        return \array_merge($summary, $failures);
    }
}
