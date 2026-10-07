<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Enum\SyncEntityEnum;
use Polkurier\Shipping\Exception\SynchronizationFailedException;
use Polkurier\Shipping\Model\Address\AddressSyncAdapter;
use Polkurier\Shipping\Model\BankAccount\BankAccountSyncAdapter;
use Polkurier\Shipping\Model\PackTemplate\PackTemplateSyncAdapter;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Psr\Log\LoggerInterface;

/**
 * Brings the address book, bank accounts and pack templates kept in Magento level with the
 * Polkurier account; the later change wins. Runs every kind of entry, or the kinds asked for. A kind
 * that can't be synchronized doesn't keep the others from it: its failure is logged and reported with
 * the others' summaries, and only when every kind asked for failed is it an error.
 */
readonly class Synchronization
{
    /**
     * @var AdapterInterface[] one per kind, in the order they run
     */
    private array $adapters;

    public function __construct(
        private SyncEngine $engine,
        private ErrorMessageResolver $errorMessageResolver,
        private SyncLock $lock,
        private LoggerInterface $logger,
        private SyncResultFactory $syncResultFactory,
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
        return $this->lock->run(fn(): array => $this->run($entities));
    }

    /**
     * Sends one entry just changed in Magento, and nothing else: no other entry is read or changed
     * on either side.
     *
     * @return SyncResult what happened to it, for the caller to report together with whatever else
     *     it did
     */
    public function push(SyncEntityEnum $kind, SyncableInterface $entry): SyncResult
    {
        return $this->lock->run(function () use ($kind, $entry): SyncResult {
            foreach ($this->adapters as $adapter) {
                if ($adapter->getEntityType() !== $kind) {
                    continue;
                }
                try {
                    return $this->engine->run($adapter, $entry);
                } catch (\Exception $e) {
                    throw new LocalizedException(
                        __('%1: %2', $kind->label(), $this->errorMessageResolver->phrase($e)),
                        $e,
                    );
                }
            }

            return $this->syncResultFactory->create(['entityType' => $kind]);
        });
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
                $summary[] = $this->engine->run($adapter)->summary();
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
