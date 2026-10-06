<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Magento\Framework\Lock\LockManagerInterface;

/**
 * Magento's locks, kept in memory: a lock is free unless this request holds it.
 */
class MemoryLocks implements LockManagerInterface
{
    /**
     * @var array<string, true>
     */
    private array $held = [];

    public function lock(string $name, int $timeout = -1): bool
    {
        if (isset($this->held[$name])) {
            return false;
        }
        $this->held[$name] = true;

        return true;
    }

    public function unlock(string $name): bool
    {
        $held = isset($this->held[$name]);
        unset($this->held[$name]);

        return $held;
    }

    public function isLocked(string $name): bool
    {
        return isset($this->held[$name]);
    }
}
