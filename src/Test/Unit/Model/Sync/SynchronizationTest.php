<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sync;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\SyncEntityEnum;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Sync\AdapterInterface;
use Polkurier\Shipping\Model\Sync\SyncEngine;
use Polkurier\Shipping\Model\Sync\Synchronization;
use Polkurier\Shipping\Model\Sync\SyncResult;
use Polkurier\Shipping\Model\WebsiteScope;

#[CoversClass(Synchronization::class)]
#[CoversClass(SyncResult::class)]
#[CoversClass(SyncEntityEnum::class)]
#[AllowMockObjectsWithoutExpectations]
class SynchronizationTest extends TestCase
{
    /**
     * @var SyncEntityEnum[]
     */
    private array $ran = [];

    public function testEveryKindRunsWhenNoneIsNamed(): void
    {
        $summary = $this->synchronization()->synchronize(1);

        self::assertSame([SyncEntityEnum::Address, SyncEntityEnum::BankAccount, SyncEntityEnum::PackTemplate], $this->ran);
        self::assertCount(3, $summary);
        self::assertStringStartsWith('Addresses:', $summary[0]);
    }

    public function testOnlyTheNamedKindsRun(): void
    {
        $summary = $this->synchronization()->synchronize(1, [SyncEntityEnum::PackTemplate]);

        self::assertSame([SyncEntityEnum::PackTemplate], $this->ran);
        self::assertStringStartsWith('Pack templates:', $summary[0]);
    }

    private function synchronization(): Synchronization
    {
        $engine = $this->createStub(SyncEngine::class);
        $engine->method('run')->willReturnCallback(function (AdapterInterface $adapter): SyncResult {
            $this->ran[] = $adapter->getEntityType();

            return new SyncResult($adapter->getEntityType());
        });

        return new Synchronization(
            $engine,
            $this->createStub(WebsiteScope::class),
            new ErrorMessages(),
            \array_map(function (SyncEntityEnum $kind): AdapterInterface {
                $adapter = $this->createStub(AdapterInterface::class);
                $adapter->method('getEntityType')->willReturn($kind);

                return $adapter;
            }, SyncEntityEnum::cases()),
        );
    }
}
