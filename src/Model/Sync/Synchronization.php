<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\SynchronizationInterface;
use Polkurier\Shipping\Enum\SyncEntityEnum;
use Polkurier\Shipping\Exception\SynchronizationFailedException;
use Polkurier\Shipping\Model\ErrorMessages;
use Psr\Log\LoggerInterface;

/**
 * Runs the sync of every kind of entry, or of the kinds asked for. A kind that
 * can't be synchronized doesn't keep the others from it: its failure is logged and reported with
 * the others' summaries, and only when every kind asked for failed is it an error.
 */
readonly class Synchronization implements SynchronizationInterface
{
    /**
     * @param AdapterInterface[] $adapters one per kind, in the order they run
     */
    public function __construct(
        private SyncEngine $engine,
        private ErrorMessages $errorMessages,
        private SyncLock $lock,
        private LoggerInterface $logger,
        private array $adapters = [],
    ) {}

    /**
     * @param SyncEntityEnum[] $entities
     *
     * @return string[]
     */
    public function synchronize(array $entities = []): array
    {
        return $this->lock->run(fn(): array => $this->run($entities));
    }

    public function push(SyncEntityEnum $kind, SyncableInterface $entry): SyncResult
    {
        return $this->lock->run(function () use ($kind, $entry): SyncResult {
            foreach ($this->adapters as $adapter) {
                if ($adapter->getEntityType() !== $kind) {
                    continue;
                }
                try {
                    return $this->engine->run($adapter, $entry);
                } catch (\Throwable $e) {
                    throw new LocalizedException(
                        \__('%1: %2', $kind->label(), $this->errorMessages->describe($e)),
                        $e instanceof \Exception ? $e : null,
                    );
                }
            }

            return new SyncResult($kind);
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
            } catch (\Throwable $e) {
                $line = (string)\__('%1: %2', $kind->label(), $this->errorMessages->describe($e));
                $this->logger->warning('Polkurier synchronization failed', ['entity' => $kind->value, 'exception' => $e]);
                $failures[] = $line;
                $lastFailure = $e instanceof \Exception ? $e : null;
            }
        }
        if ($summary === [] && $failures !== []) {
            // Each line is translated already.
            throw new SynchronizationFailedException(new Phrase(\implode(' ', $failures)), $lastFailure);
        }

        return \array_merge($summary, $failures);
    }
}
