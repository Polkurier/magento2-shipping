<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * One writer at a time for the synchronized entries: the hourly synchronization and saving in the
 * admin, which synchronizes straight away. Two synchronizations at once would both send a new
 * entry, and one would save rows the other had changed in between.
 */
class SyncLock implements ResetAfterRequestInterface
{
    public const string NAME = 'polkurier_sync';
    public const int TIMEOUT_SECONDS = 20;

    /**
     * Whether the lock is held here.
     */
    private bool $held = false;

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
        if (!$this->lockManager->lock(self::NAME, self::TIMEOUT_SECONDS)) {
            throw new LocalizedException(__(
                'A synchronization with Polkurier is already running. Try again in a moment.',
            ));
        }
        $this->held = true;
        try {
            return $work();
        } finally {
            // Unless the request was reset meanwhile, which released it already.
            $this->_resetState();
        }
    }

    /**
     * The lock, if still held when the request ends, as after a fatal error, is released.
     */
    public function _resetState(): void
    {
        if ($this->held) {
            $this->lockManager->unlock(self::NAME);
        }
        $this->held = false;
    }
}
