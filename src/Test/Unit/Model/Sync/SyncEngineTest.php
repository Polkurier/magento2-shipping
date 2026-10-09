<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sync;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Enum\Sync\SyncEntityEnum;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\PackTemplate;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sync\AdapterInterface;
use Polkurier\Shipping\Model\Sync\SyncEngine;
use Polkurier\Shipping\Model\Sync\SyncResultFactory;
use Polkurier\Shipping\Test\Unit\Support\FakePushableSyncAdapter;
use Polkurier\Shipping\Test\Unit\Support\FakeSyncAdapter;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;

#[CoversClass(SyncEngine::class)]
#[AllowMockObjectsWithoutExpectations]
class SyncEngineTest extends TestCase
{
    private const string LAST_SYNC = '2026-09-20 10:00:00';
    private const string NOW = '2026-09-25 12:00:00';

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $remote = [];

    /**
     * @var array<int, PackTemplate>
     */
    private array $local = [];

    /**
     * @var string[]
     */
    private array $log = [];

    /**
     * @var string[] what was saved, as "<name>: <sync state>"
     */
    private array $saved = [];

    /**
     * @var array<int, array{0: string, 1: string, 2: array<string, mixed>}> level, message and
     *     context of what was logged
     */
    private array $logged = [];

    private bool $canPush = true;
    private ?string $failingPush = null;

    /**
     * The name of the entry whose save as synchronized fails, once.
     */
    private ?string $failingLink = null;

    public function testNewRemoteEntriesArePulled(): void
    {
        $this->remote['r1'] = $this->remoteEntry('r1', 'Box S');

        $result = $this->synchronize();

        self::assertSame(1, $result->created);
        $pulled = \array_values($this->local)[0];
        self::assertSame('r1', $pulled->getExtId());
        self::assertSame('Box S', $pulled->getName());
        self::assertSame(SyncableInterface::STATE_SYNCED, $pulled->getSyncState());
    }

    public function testNewLocalEntriesArePushed(): void
    {
        $this->addLocal(null, 'Box M', updatedAt: self::NOW, syncedAt: null);

        $result = $this->synchronize();

        self::assertSame(1, $result->pushed);
        self::assertSame(['push:Box M'], $this->log);
        self::assertSame('new-1', $this->local[1]->getExtId());
    }

    public function testALocalChangeIsPushedAndARemoteChangeIsPulled(): void
    {
        $this->remote['r1'] = $this->remoteEntry('r1', 'Box S');
        $this->remote['r2'] = $this->remoteEntry('r2', 'Box L renamed');
        $this->addLocal(
            'r1',
            'Box S edited',
            updatedAt: '2026-09-24 09:00:00',
            hashOf: $this->remoteEntry('r1', 'Box S'),
        );
        $this->addLocal('r2', 'Box L', hashOf: $this->remoteEntry('r2', 'Box L'));

        $result = $this->synchronize();

        self::assertSame(1, $result->pushed);
        self::assertSame(1, $result->pulled);
        self::assertSame(['push:Box S edited'], $this->log);
        self::assertSame('Box L renamed', $this->local[2]->getName());
    }

    public function testAnEditInTheSameSecondAsTheLastSynchronizationIsPushed(): void
    {
        $this->remote['r1'] = $this->remoteEntry('r1', 'Box S');
        $this->addLocal(
            'r1',
            'Box S edited',
            hashOf: $this->remoteEntry('r1', 'Box S'),
            state: SyncableInterface::STATE_PENDING,
        );

        $result = $this->synchronize();

        self::assertSame(1, $result->pushed);
        self::assertSame(['push:Box S edited'], $this->log);
        self::assertSame(SyncableInterface::STATE_SYNCED, $this->local[1]->getSyncState());
    }

    public function testAChangeThatFailedToGoThroughIsSentAgain(): void
    {
        $this->remote['r1'] = $this->remoteEntry('r1', 'Box S');
        $this->addLocal(
            'r1',
            'Box S edited',
            hashOf: $this->remoteEntry('r1', 'Box S'),
            state: SyncableInterface::STATE_ERROR,
        );

        $this->synchronize();

        self::assertSame(['push:Box S edited'], $this->log);
    }

    public function testAnEntryFailingForAnyReasonDoesNotStopTheOthers(): void
    {
        $this->failingPush = 'push:Box M';
        $this->addLocal(null, 'Box M', updatedAt: self::NOW, syncedAt: null);
        $this->addLocal(null, 'Box L', updatedAt: self::NOW, syncedAt: null);

        $result = $this->synchronize();

        self::assertSame(1, $result->failed);
        self::assertSame(1, $result->pushed);
        self::assertSame(['push:Box L'], $this->log);
        self::assertSame(SyncableInterface::STATE_ERROR, $this->local[1]->getSyncState());
    }

    public function testAnEntrySentWhoseLinkCannotBeSavedIsLoggedWithItsRemoteIdAndMarkedInError(): void
    {
        $this->failingLink = 'Box M';
        $this->addLocal(null, 'Box M', updatedAt: self::NOW, syncedAt: null);
        $this->addLocal(null, 'Box L', updatedAt: self::NOW, syncedAt: null);

        $result = $this->synchronize();

        self::assertSame(['push:Box M', 'push:Box L'], $this->log, 'The other entries carry on.');
        self::assertContains(
            [
                'error',
                'Polkurier entry sent but saving it as synced failed',
                ['entity' => SyncEntityEnum::PackTemplate->value, 'id' => 1, 'remote' => 'new-1'],
            ],
            $this->logged,
            'The log says which Polkurier entry it is.',
        );
        self::assertSame(1, $result->failed);
        self::assertCount(1, $result->errors);
        self::assertSame(1, $result->pushed);
        self::assertSame(
            ['Box M: ' . SyncableInterface::STATE_ERROR, 'Box L: ' . SyncableInterface::STATE_SYNCED],
            $this->saved,
        );
    }

    public function testAnEntrySentWhoseLinkWasNotSavedIsLinkedNotSentAgain(): void
    {
        $this->remote['r7'] = $this->remoteEntry('r7', 'Box M');
        $this->addLocal(null, 'Box M', updatedAt: self::NOW, syncedAt: null);

        $result = $this->synchronize();

        self::assertSame('r7', $this->local[1]->getExtId(), 'Sent as the entry Polkurier has, not as a new one.');
        self::assertSame(0, $result->created, 'Nor pulled as a new entry.');
        self::assertCount(1, $this->local);
    }

    public function testTwoRemoteEntriesOfTheSameNameAreNotGuessedBetween(): void
    {
        $this->remote['r7'] = $this->remoteEntry('r7', 'Box M');
        $this->remote['r8'] = $this->remoteEntry('r8', 'Box M');
        $this->addLocal(null, 'Box M', updatedAt: self::NOW, syncedAt: null);

        $this->synchronize();

        self::assertSame(['push:Box M'], $this->log);
    }

    public function testOneEntrySavedInTheAdminIsSentAlone(): void
    {
        $this->remote['r1'] = $this->remoteEntry('r1', 'Box S');
        $this->remote['r2'] = $this->remoteEntry('r2', 'Box L renamed');
        $this->remote['r3'] = $this->remoteEntry('r3', 'Box XL');
        $saved = $this->addLocal(
            'r1',
            'Box S edited',
            hashOf: $this->remoteEntry('r1', 'Box S'),
            state: SyncableInterface::STATE_PENDING,
        );
        $this->addLocal('r2', 'Box L', hashOf: $this->remoteEntry('r2', 'Box L'));
        $this->addLocal(null, 'Box M', updatedAt: self::NOW, syncedAt: null);

        $result = $this->synchronize($saved);

        self::assertSame(['push:Box S edited'], $this->log, 'The unsent Box M waits for the next full run.');
        self::assertSame('Box L', $this->local[2]->getName(), 'The remote change is left for it too.');
        self::assertCount(3, $this->local, 'And the new remote entry.');
        self::assertSame(1, $result->pushed);
    }

    public function testWhenBothChangedTheLaterChangeWins(): void
    {
        // Remote change detected now; until the API reports its time, now counts as its time.
        $this->remote['r1'] = $this->remoteEntry('r1', 'Box S remote');
        $this->addLocal(
            'r1',
            'Box S local',
            updatedAt: '2026-09-24 09:00:00',
            hashOf: $this->remoteEntry('r1', 'Box S'),
        );

        $this->synchronize();

        self::assertSame('Box S remote', $this->local[1]->getName());
        self::assertSame([], $this->log);
    }

    public function testALocalEditAfterTheRemoteChangeWins(): void
    {
        $this->remote['r1'] = $this->remoteEntry('r1', 'Box S remote');
        $this->addLocal(
            'r1',
            'Box S local',
            updatedAt: '2026-09-26 08:00:00',
            hashOf: $this->remoteEntry('r1', 'Box S'),
        );

        $this->synchronize();

        self::assertSame(['push:Box S local'], $this->log);
    }

    public function testAnEntryDeletedRemotelyIsDeletedLocallyUnlessEditedSince(): void
    {
        $this->addLocal('gone', 'Untouched', hashOf: $this->remoteEntry('gone', 'Untouched'));
        $this->addLocal(
            'gone2',
            'Edited',
            updatedAt: '2026-09-24 09:00:00',
            hashOf: $this->remoteEntry('gone2', 'Edited'),
        );

        $result = $this->synchronize();

        self::assertSame(1, $result->deleted);
        self::assertSame(1, $result->pushed);
        self::assertArrayNotHasKey(1, $this->local);
        self::assertSame(['push:Edited'], $this->log);
    }

    public function testALocalDeletionIsCarriedToPolkurier(): void
    {
        $this->remote['r1'] = $this->remoteEntry('r1', 'Box S');
        $this->addLocal('r1', 'Box S', hashOf: $this->remoteEntry('r1', 'Box S'))->setDeletedAt('2026-09-24 09:00:00');

        $result = $this->synchronize();

        self::assertSame(1, $result->deleted);
        self::assertSame(['delete:r1'], $this->log);
        self::assertSame([], $this->local);
    }

    public function testANewEntryThatNeedsConfirmationWaitsForIt(): void
    {
        $this->canPush = false;
        $this->addLocal(null, 'Own account', updatedAt: self::NOW, syncedAt: null);

        $result = $this->synchronize();

        self::assertSame(1, $result->unconfirmed);
        self::assertSame(SyncableInterface::STATE_UNCONFIRMED, $this->local[1]->getSyncState());
        self::assertSame([], $this->log);
    }

    public function testAnUnconfirmedEditIsKeptWhilePolkurierHasNothingNewer(): void
    {
        $this->canPush = false;
        $this->remote['r1'] = $this->remoteEntry('r1', 'Box S');
        $this->addLocal(
            'r1',
            'Box S edited',
            updatedAt: '2026-09-24 09:00:00',
            hashOf: $this->remoteEntry('r1', 'Box S'),
        );

        $result = $this->synchronize();

        self::assertSame(1, $result->unconfirmed);
        self::assertSame('Box S edited', $this->local[1]->getName(), 'Not overwritten, and not marked as in sync.');
        self::assertSame(SyncableInterface::STATE_UNCONFIRMED, $this->local[1]->getSyncState());
        self::assertSame([], $this->log);
    }

    public function testANewerRemoteChangeStillWinsOverAnUnconfirmedEdit(): void
    {
        $this->canPush = false;
        $this->remote['r1'] = $this->remoteEntry('r1', 'Box S remote');
        $this->addLocal(
            'r1',
            'Box S local',
            updatedAt: '2026-09-24 09:00:00',
            hashOf: $this->remoteEntry('r1', 'Box S'),
        );

        $this->synchronize();

        self::assertSame('Box S remote', $this->local[1]->getName());
    }

    public function testAnUnconfirmedDeletionIsKeptInsteadOfComingBack(): void
    {
        $this->canPush = false;
        $this->remote['r1'] = $this->remoteEntry('r1', 'Box S');
        $this->addLocal('r1', 'Box S', hashOf: $this->remoteEntry('r1', 'Box S'))->setDeletedAt('2026-09-24 09:00:00');

        $result = $this->synchronize();

        self::assertSame(1, $result->unconfirmed);
        self::assertArrayHasKey(1, $this->local, 'Deleting only the Magento copy would bring it back.');
        self::assertNotNull($this->local[1]->getDeletedAt());
        self::assertSame([], $this->log);
    }

    /**
     * The fake adapter's window onto the test's state.
     */
    public function state(string $what, mixed $value = null): mixed
    {
        switch ($what) {
            case 'remote':
                return $this->remote;
            case 'local':
                return $this->local;
            case 'log':
                if ((string)$value === $this->failingPush) {
                    throw new \RuntimeException('Disk full.');
                }
                $this->log[] = (string)$value;

                return null;
            case 'create':
                $entity = (new ObjectManager($this))->getObject(PackTemplate::class);
                $entity->setId(\count($this->local) + 100);

                return $entity;
            case 'save':
                if ($value->getName() === $this->failingLink
                    && $value->getSyncState() === SyncableInterface::STATE_SYNCED
                ) {
                    $this->failingLink = null;
                    throw new \RuntimeException('Deadlock found.');
                }
                $this->saved[] = $value->getName() . ': ' . $value->getSyncState();
                $this->local[(int)$value->getId()] = $value;

                return null;
            case 'delete':
                unset($this->local[(int)$value->getId()]);

                return null;
        }

        return null;
    }

    private function synchronize(?SyncableInterface $only = null): \Polkurier\Shipping\Model\Sync\SyncResult
    {
        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn((int)\strtotime(self::NOW . ' UTC'));
        $logger = $this->createStub(Logger::class);
        foreach (['error', 'warning'] as $level) {
            $logger->method($level)
                ->willReturnCallback(function (string|\Stringable $message, array $context = []) use ($level): void {
                    $this->logged[] = [$level, (string)$message, $context];
                });
        }
        $engine = new SyncEngine(
            new ErrorMessageResolver(),
            $dateTime,
            $logger,
            NewingObjectManager::factory(SyncResultFactory::class),
        );

        return $engine->run($this->adapter(), $only);
    }

    /**
     * @param array<string, mixed>|null $hashOf the remote entry as the last synchronization saw it
     */
    private function addLocal(
        ?string $extId,
        string $name,
        ?string $updatedAt = self::LAST_SYNC,
        ?string $syncedAt = self::LAST_SYNC,
        ?array $hashOf = null,
        string $state = SyncableInterface::STATE_SYNCED,
    ): PackTemplate {
        $entity = (new ObjectManager($this))->getObject(PackTemplate::class);
        $entity->setId(\count($this->local) + 1);
        $entity->setExtId($extId)->setName($name)
            ->setUpdatedAt($updatedAt)->setSyncedAt($syncedAt)->setSyncState($state)
            ->setRemoteHash($hashOf !== null ? $this->hash($hashOf) : null)
            ->setRemoteUpdatedAt($syncedAt);
        $this->local[(int)$entity->getId()] = $entity;

        return $entity;
    }

    /**
     * @return array<string, mixed>
     */
    private function remoteEntry(string $id, string $name): array
    {
        return [SyncableInterface::FIELD_EXT_ID => $id, SyncableInterface::FIELD_NAME => $name];
    }

    /**
     * @param array<string, mixed> $remote
     */
    private function hash(array $remote): string
    {
        $dateTime = $this->createStub(DateTime::class);

        return (new SyncEngine(
            new ErrorMessageResolver(),
            $dateTime,
            $this->createStub(Logger::class),
            NewingObjectManager::factory(SyncResultFactory::class),
        ))->hash($remote);
    }

    private function adapter(): AdapterInterface
    {
        $state = \Closure::fromCallable([$this, 'state']);

        return $this->canPush ? new FakePushableSyncAdapter($state) : new FakeSyncAdapter($state);
    }
}
