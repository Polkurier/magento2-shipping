<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sync;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Enum\Sync\SyncEntityEnum;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Address\AddressSyncAdapter;
use Polkurier\Shipping\Model\BankAccount\BankAccountSyncAdapter;
use Polkurier\Shipping\Model\PackTemplate\PackTemplateSyncAdapter;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sync\AdapterInterface;
use Polkurier\Shipping\Model\Sync\SyncEngine;
use Polkurier\Shipping\Model\Sync\Synchronization;
use Polkurier\Shipping\Model\Sync\SyncLock;
use Polkurier\Shipping\Model\Sync\SyncResult;

#[CoversClass(Synchronization::class)]
#[CoversClass(SyncResult::class)]
#[CoversClass(SyncEntityEnum::class)]
#[CoversClass(SyncLock::class)]
#[AllowMockObjectsWithoutExpectations]
class SynchronizationTest extends TestCase
{
    /**
     * @var SyncEntityEnum[]
     */
    private array $ran = [];
    private bool $busy = false;

    /**
     * @var SyncEntityEnum[]
     */
    private array $failing = [];

    /**
     * @var string[]
     */
    private array $lockLog = [];

    public function testEveryKindRunsWhenNoneIsNamed(): void
    {
        $summary = $this->createSynchronization()->synchronize();

        self::assertSame(
            [SyncEntityEnum::Address, SyncEntityEnum::BankAccount, SyncEntityEnum::PackTemplate],
            $this->ran,
        );
        self::assertCount(3, $summary);
        self::assertStringStartsWith('Addresses:', $summary[0]);
    }

    public function testOnlyTheNamedKindsRun(): void
    {
        $summary = $this->createSynchronization()->synchronize([SyncEntityEnum::PackTemplate]);

        self::assertSame([SyncEntityEnum::PackTemplate], $this->ran);
        self::assertStringStartsWith('Pack templates:', $summary[0]);
    }

    public function testNothingRunsWhileAnotherSynchronizationIsRunning(): void
    {
        $this->busy = true;

        try {
            $this->createSynchronization()->synchronize();
            self::fail('A synchronization is running.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('already running', $e->getMessage());
        }
        self::assertSame([], $this->ran);
    }

    public function testTheLockIsFreeAgainAfterwards(): void
    {
        $this->createSynchronization()->synchronize([SyncEntityEnum::PackTemplate]);

        self::assertSame(['lock polkurier_sync', 'unlock polkurier_sync'], $this->lockLog);
    }

    public function testAKindThatFailsDoesNotKeepTheOthersFromTheirRun(): void
    {
        $this->failing = [SyncEntityEnum::Address];

        $summary = $this->createSynchronization()->synchronize();

        self::assertSame(
            [SyncEntityEnum::Address, SyncEntityEnum::BankAccount, SyncEntityEnum::PackTemplate],
            $this->ran,
        );
        self::assertCount(3, $summary);
        self::assertSame('Addresses: Unreadable.', $summary[2], 'Reported after the others.');
    }

    public function testWhenEveryKindFailsItIsAnError(): void
    {
        $this->failing = [SyncEntityEnum::PackTemplate];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Pack templates: Unreadable.');

        $this->createSynchronization()->synchronize([SyncEntityEnum::PackTemplate]);
    }

    private function createLockManager(): LockManagerInterface
    {
        $locks = $this->createStub(LockManagerInterface::class);
        $locks->method('lock')->willReturnCallback(function (string $name): bool {
            $this->lockLog[] = 'lock ' . $name;

            return !$this->busy;
        });
        $locks->method('unlock')->willReturnCallback(function (string $name): bool {
            $this->lockLog[] = 'unlock ' . $name;

            return true;
        });

        return $locks;
    }

    private function createSynchronization(): Synchronization
    {
        $engine = $this->createStub(SyncEngine::class);
        $engine->method('run')
            ->willReturnCallback(function (AdapterInterface $adapter): SyncResult {
                $this->ran[] = $adapter->getEntityType();
                if (\in_array($adapter->getEntityType(), $this->failing, true)) {
                    throw new \RuntimeException('Unreadable.');
                }

                return new SyncResult($adapter->getEntityType());
            });

        return new Synchronization(
            $engine,
            new ErrorMessageResolver(),
            new SyncLock($this->createLockManager()),
            $this->createStub(Logger::class),
            $this->createAdapter(AddressSyncAdapter::class, SyncEntityEnum::Address),
            $this->createAdapter(BankAccountSyncAdapter::class, SyncEntityEnum::BankAccount),
            $this->createAdapter(PackTemplateSyncAdapter::class, SyncEntityEnum::PackTemplate),
        );
    }

    /**
     * @template T of AdapterInterface
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function createAdapter(string $class, SyncEntityEnum $kind): AdapterInterface
    {
        $adapter = $this->createStub($class);
        $adapter->method('getEntityType')->willReturn($kind);

        return $adapter;
    }
}
