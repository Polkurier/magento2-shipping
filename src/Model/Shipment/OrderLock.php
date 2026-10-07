<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * One change at a time to an order's Polkurier shipments: placing, reconciling, linking by hand,
 * refreshing, shipping in Magento and cancelling. Two at once, such as the scheduled reconciliation
 * and the admin's, or a webhook and the scheduled update, would both make a Magento shipment for
 * the same Polkurier order. Polkurier is asked before the lock is taken, and the shipment is read
 * again under it.
 *
 * Re-entrant within a request, per order.
 */
class OrderLock implements ResetAfterRequestInterface
{
    public const int TIMEOUT_SECONDS = 10;

    private const string PREFIX = 'polkurier_order_';

    /**
     * How many times each order's lock is held here.
     *
     * @var array<int, int>
     */
    private array $held = [];

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
    public function run(int $orderId, callable $work): mixed
    {
        $this->lock($orderId);
        try {
            return $work();
        } finally {
            $this->unlock($orderId);
        }
    }

    /**
     * Takes the order's lock, for a caller that can't hand its work over as one callable; release
     * it with unlock() in a `finally`.
     *
     * @throws LocalizedException when another request keeps the lock for too long
     */
    public function lock(int $orderId): void
    {
        $held = $this->held[$orderId] ?? 0;
        if ($held === 0 && !$this->lockManager->lock(self::PREFIX . $orderId, self::TIMEOUT_SECONDS)) {
            throw new LocalizedException(__(
                "Another request is updating this order's Polkurier shipments. Wait a moment and try again.",
            ));
        }
        $this->held[$orderId] = $held + 1;
    }

    public function unlock(int $orderId): void
    {
        if (!isset($this->held[$orderId])) {
            return;
        }
        if (--$this->held[$orderId] === 0) {
            unset($this->held[$orderId]);
            $this->lockManager->unlock(self::PREFIX . $orderId);
        }
    }

    /**
     * Locks still held when the request ends, as after a fatal error, are let go.
     */
    public function _resetState(): void
    {
        foreach (\array_keys($this->held) as $orderId) {
            $this->lockManager->unlock(self::PREFIX . $orderId);
        }
        $this->held = [];
    }
}
