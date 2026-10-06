<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

/**
 * One writer at a time for the synchronized entries: the hourly synchronization and saving in the
 * admin, which synchronizes straight away. Two synchronizations at once would both send a new
 * entry, and one would save rows the other had changed in between.
 *
 * Re-entrant within a request: saving in the admin holds it while it synchronizes.
 */
class SyncLock
{
    public const string NAME = 'polkurier_sync';
    public const int TIMEOUT_SECONDS = 20;

    /**
     * How many times the lock is held here.
     */
    private int $held = 0;

    public function __construct(
        private readonly LockManagerInterface $lockManager,
    ) {}

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     * @throws LocalizedException when another request keeps the lock for too long
     */
    public function run(callable $work): mixed
    {
        if ($this->held === 0 && !$this->lockManager->lock(self::NAME, self::TIMEOUT_SECONDS)) {
            throw new LocalizedException(\__('A synchronization with Polkurier is already running. Try again in a moment.'));
        }
        $this->held++;
        try {
            return $work();
        } finally {
            if (--$this->held === 0) {
                $this->lockManager->unlock(self::NAME);
            }
        }
    }
}
