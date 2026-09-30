<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sync;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\SyncEntityEnum;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Sync\AdapterInterface;
use Polkurier\Shipping\Model\Sync\SyncEngine;
use Polkurier\Shipping\Model\Sync\Synchronization;
use Polkurier\Shipping\Model\Sync\SyncLock;
use Psr\Log\NullLogger;
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
     * @var array<int, SyncableInterface|null>
     */
    private array $only = [];

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
        $summary = $this->synchronization()->synchronize();

        self::assertSame([SyncEntityEnum::Address, SyncEntityEnum::BankAccount, SyncEntityEnum::PackTemplate], $this->ran);
        self::assertCount(3, $summary);
        self::assertStringStartsWith('Addresses:', $summary[0]);
    }

    public function testOnlyTheNamedKindsRun(): void
    {
        $summary = $this->synchronization()->synchronize([SyncEntityEnum::PackTemplate]);

        self::assertSame([SyncEntityEnum::PackTemplate], $this->ran);
        self::assertStringStartsWith('Pack templates:', $summary[0]);
    }

    public function testNothingRunsWhileAnotherSynchronizationIsRunning(): void
    {
        $this->busy = true;

        try {
            $this->synchronization()->synchronize();
            self::fail('A synchronization is running.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('already running', $e->getMessage());
        }
        self::assertSame([], $this->ran);
    }

    public function testTheLockIsFreeAgainAfterwards(): void
    {
        $this->synchronization()->synchronize([SyncEntityEnum::PackTemplate]);

        self::assertSame(['lock polkurier_sync', 'unlock polkurier_sync'], $this->lockLog);
    }

    public function testAnEntrySavedInTheAdminIsPushedAloneUnderTheLock(): void
    {
        $entry = $this->createStub(SyncableInterface::class);

        $summary = $this->synchronization()->push(SyncEntityEnum::PackTemplate, $entry);

        self::assertSame([SyncEntityEnum::PackTemplate], $this->ran);
        self::assertSame([$entry], $this->only);
        self::assertCount(1, $summary);
        self::assertSame(['lock polkurier_sync', 'unlock polkurier_sync'], $this->lockLog);
    }

    public function testAKindThatFailsDoesNotKeepTheOthersFromTheirRun(): void
    {
        $this->failing = [SyncEntityEnum::Address];

        $summary = $this->synchronization()->synchronize();

        self::assertSame([SyncEntityEnum::Address, SyncEntityEnum::BankAccount, SyncEntityEnum::PackTemplate], $this->ran);
        self::assertCount(3, $summary);
        self::assertSame('Addresses: Unreadable.', $summary[2], 'Reported after the others.');
    }

    public function testWhenEveryKindFailsItIsAnError(): void
    {
        $this->failing = [SyncEntityEnum::PackTemplate];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Pack templates: Unreadable.');

        $this->synchronization()->synchronize([SyncEntityEnum::PackTemplate]);
    }

    private function locks(): LockManagerInterface
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

    private function synchronization(): Synchronization
    {
        $engine = $this->createStub(SyncEngine::class);
        $engine->method('run')->willReturnCallback(function (AdapterInterface $adapter, ?SyncableInterface $only = null): SyncResult {
            $this->ran[] = $adapter->getEntityType();
            $this->only[] = $only;
            if (\in_array($adapter->getEntityType(), $this->failing, true)) {
                throw new \RuntimeException('Unreadable.');
            }

            return new SyncResult($adapter->getEntityType());
        });

        return new Synchronization(
            $engine,
            new ErrorMessages(),
            new SyncLock($this->locks()),
            new NullLogger(),
            \array_map(function (SyncEntityEnum $kind): AdapterInterface {
                $adapter = $this->createStub(AdapterInterface::class);
                $adapter->method('getEntityType')->willReturn($kind);

                return $adapter;
            }, SyncEntityEnum::cases()),
        );
    }
}
