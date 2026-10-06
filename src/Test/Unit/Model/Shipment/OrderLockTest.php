<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\OrderLock;

#[CoversClass(OrderLock::class)]
#[AllowMockObjectsWithoutExpectations]
class OrderLockTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $log = [];
    private bool $fail = false;

    public function testWorkInsideWorkOnTheSameOrderTakesTheLockOnce(): void
    {
        $lock = $this->lock();

        $result = $lock->run(42, fn(): string => $lock->run(42, fn(): string => 'shipped'));

        self::assertSame('shipped', $result);
        self::assertSame(['lock polkurier_order_42', 'unlock polkurier_order_42'], $this->log);
    }

    public function testEachOrderHasALockOfItsOwn(): void
    {
        $lock = $this->lock();

        $lock->run(42, fn(): bool => $lock->run(43, static fn(): bool => true));

        self::assertSame(['lock polkurier_order_42', 'lock polkurier_order_43', 'unlock polkurier_order_43', 'unlock polkurier_order_42'], $this->log);
    }

    public function testAnotherRequestHoldingItIsReported(): void
    {
        try {
            $this->lock(taken: true)->run(42, static fn(): bool => true);
            self::fail('The work waits for the other request.');
        } catch (LocalizedException $e) {
            self::assertSame("Another request is updating this order's Polkurier shipments. Wait a moment and try again.", $e->getMessage());
        }
        self::assertSame(['lock polkurier_order_42'], $this->log);
    }

    public function testTheLockIsReleasedWhenTheWorkFails(): void
    {
        $lock = $this->lock();
        $this->fail = true;
        $work = function (): bool {
            if ($this->fail) {
                throw new \RuntimeException('Failed.');
            }

            return true;
        };

        try {
            $lock->run(42, $work);
        } catch (\Throwable) {
        }
        $this->fail = false;
        $lock->run(42, $work);

        self::assertSame(\array_merge(...\array_fill(0, 2, ['lock polkurier_order_42', 'unlock polkurier_order_42'])), $this->log);
    }

    public function testUnlockingALockNotHeldDoesNothing(): void
    {
        $this->lock()->unlock(42);

        self::assertSame([], $this->log);
    }

    private function lock(bool $taken = false): OrderLock
    {
        $locks = $this->createStub(LockManagerInterface::class);
        $locks->method('lock')->willReturnCallback(function (string $name) use ($taken): bool {
            $this->log[] = 'lock ' . $name;

            return !$taken;
        });
        $locks->method('unlock')->willReturnCallback(function (string $name): bool {
            $this->log[] = 'unlock ' . $name;

            return true;
        });

        return new OrderLock($locks);
    }
}
