<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sync;

use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Sync\SyncLock;

#[CoversClass(SyncLock::class)]
#[AllowMockObjectsWithoutExpectations]
class SyncLockTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $log = [];
    private bool $fail = false;

    public function testASaveThatSynchronizesTakesTheLockOnce(): void
    {
        $lock = $this->lock();

        $result = $lock->run(fn(): string => $lock->run(fn(): string => 'synchronized'));

        self::assertSame('synchronized', $result);
        self::assertSame(['lock polkurier_sync', 'unlock polkurier_sync'], $this->log);
    }

    public function testAnotherRequestHoldingItIsReported(): void
    {
        $this->fail = true;

        try {
            $this->lock(taken: true)->run(static fn(): bool => true);
            self::fail('The work waits for the other request.');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            self::assertStringContainsString('already running', $e->getMessage());
        }
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
            $lock->run($work);
        } catch (\Throwable) {
        }
        $this->fail = false;
        $lock->run($work);

        self::assertSame(
            ['lock polkurier_sync', 'unlock polkurier_sync', 'lock polkurier_sync', 'unlock polkurier_sync'],
            $this->log,
        );
    }

    public function testALockStillHeldWhenTheRequestEndsIsLetGoOnce(): void
    {
        $lock = $this->lock();

        $lock->run(static fn() => $lock->_resetState());
        $lock->run(static fn(): bool => true);

        self::assertSame(
            ['lock polkurier_sync', 'unlock polkurier_sync', 'lock polkurier_sync', 'unlock polkurier_sync'],
            $this->log,
        );
    }

    private function lock(bool $taken = false): SyncLock
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

        return new SyncLock($locks);
    }
}
