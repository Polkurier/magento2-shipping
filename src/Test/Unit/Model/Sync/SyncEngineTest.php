<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sync;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\SyncEntityEnum;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\PackTemplate;
use Polkurier\Shipping\Model\Sync\AdapterInterface;
use Polkurier\Shipping\Model\Sync\DetectedChangeClock;
use Polkurier\Shipping\Model\Sync\SyncEngine;
use Psr\Log\NullLogger;

#[CoversClass(SyncEngine::class)]
#[CoversClass(DetectedChangeClock::class)]
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
    private bool $canPush = true;

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
        $this->addLocal('r1', 'Box S edited', updatedAt: '2026-09-24 09:00:00', hashOf: $this->remoteEntry('r1', 'Box S'));
        $this->addLocal('r2', 'Box L', hashOf: $this->remoteEntry('r2', 'Box L'));

        $result = $this->synchronize();

        self::assertSame(1, $result->pushed);
        self::assertSame(1, $result->pulled);
        self::assertSame(['push:Box S edited'], $this->log);
        self::assertSame('Box L renamed', $this->local[2]->getName());
    }

    public function testWhenBothChangedTheLaterChangeWins(): void
    {
        // Remote change detected now; until the API reports its time, now counts as its time.
        $this->remote['r1'] = $this->remoteEntry('r1', 'Box S remote');
        $this->addLocal('r1', 'Box S local', updatedAt: '2026-09-24 09:00:00', hashOf: $this->remoteEntry('r1', 'Box S'));

        $this->synchronize();

        self::assertSame('Box S remote', $this->local[1]->getName());
        self::assertSame([], $this->log);
    }

    public function testALocalEditAfterTheRemoteChangeWins(): void
    {
        $this->remote['r1'] = $this->remoteEntry('r1', 'Box S remote');
        $this->addLocal('r1', 'Box S local', updatedAt: '2026-09-26 08:00:00', hashOf: $this->remoteEntry('r1', 'Box S'));

        $this->synchronize();

        self::assertSame(['push:Box S local'], $this->log);
    }

    public function testAnEntryDeletedRemotelyIsDeletedLocallyUnlessEditedSince(): void
    {
        $this->addLocal('gone', 'Untouched', hashOf: $this->remoteEntry('gone', 'Untouched'));
        $this->addLocal('gone2', 'Edited', updatedAt: '2026-09-24 09:00:00', hashOf: $this->remoteEntry('gone2', 'Edited'));

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
        $this->addLocal('r1', 'Box S edited', updatedAt: '2026-09-24 09:00:00', hashOf: $this->remoteEntry('r1', 'Box S'));

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
        $this->addLocal('r1', 'Box S local', updatedAt: '2026-09-24 09:00:00', hashOf: $this->remoteEntry('r1', 'Box S'));

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

    private function synchronize(): \Polkurier\Shipping\Model\Sync\SyncResult
    {
        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn((int)\strtotime(self::NOW . ' UTC'));
        $engine = new SyncEngine(new DetectedChangeClock(), new ErrorMessages(), $dateTime, new NullLogger());

        return $engine->run($this->adapter(), 1, 1);
    }

    /**
     * @param array<string, mixed>|null $hashOf the remote entry as the last synchronization saw it
     */
    private function addLocal(?string $extId, string $name, ?string $updatedAt = self::LAST_SYNC, ?string $syncedAt = self::LAST_SYNC, ?array $hashOf = null): PackTemplate
    {
        $entity = (new ObjectManager($this))->getObject(PackTemplate::class);
        $entity->setId(\count($this->local) + 1);
        $entity->setWebsiteId(1)->setExtId($extId)->setName($name)
            ->setUpdatedAt($updatedAt)->setSyncedAt($syncedAt)
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
        return ['id' => $id, 'name' => $name];
    }

    /**
     * @param array<string, mixed> $remote
     */
    private function hash(array $remote): string
    {
        $dateTime = $this->createStub(DateTime::class);

        return (new SyncEngine(new DetectedChangeClock(), new ErrorMessages(), $dateTime, new NullLogger()))->hash($remote);
    }

    private function adapter(): AdapterInterface
    {
        $test = $this;

        return new class ($test) implements AdapterInterface {
            public function __construct(private readonly SyncEngineTest $test)
            {
            }

            public function getEntityType(): SyncEntityEnum
            {
                return SyncEntityEnum::PackTemplate;
            }

            public function fetchRemote(int $storeId): array
            {
                return $this->test->state('remote');
            }

            public function fetchLocal(int $websiteId): array
            {
                return \array_values($this->test->state('local'));
            }

            public function createLocal(int $websiteId): SyncableInterface
            {
                return $this->test->state('create');
            }

            public function applyRemote(SyncableInterface $local, array $remote): void
            {
                /** @var PackTemplate $local */
                $local->setExtId((string)$remote['id'])->setName((string)$remote['name']);
            }

            public function canPush(): bool
            {
                return $this->test->state('canPush');
            }

            public function push(SyncableInterface $local, int $storeId): array
            {
                /** @var PackTemplate $local */
                $this->test->state('log', 'push:' . $local->getName());

                return ['id' => $local->getExtId() ?? 'new-' . $local->getId(), 'name' => $local->getName()];
            }

            public function deleteRemote(SyncableInterface $local, int $storeId): void
            {
                $this->test->state('log', 'delete:' . $local->getExtId());
            }

            public function save(SyncableInterface $local): void
            {
                $this->test->state('save', $local);
            }

            public function delete(SyncableInterface $local): void
            {
                $this->test->state('delete', $local);
            }
        };
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
            case 'canPush':
                return $this->canPush;
            case 'log':
                $this->log[] = (string)$value;

                return null;
            case 'create':
                $entity = (new ObjectManager($this))->getObject(PackTemplate::class);
                $entity->setId(\count($this->local) + 100);

                return $entity;
            case 'save':
                $this->local[(int)$value->getId()] = $value;

                return null;
            case 'delete':
                unset($this->local[(int)$value->getId()]);

                return null;
        }

        return null;
    }
}
