<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Admin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\SyncEntityEnum;
use Polkurier\Shipping\Api\SynchronizationInterface;
use Polkurier\Shipping\Model\Sync\SyncLock;
use Polkurier\Shipping\Model\Sync\SyncResult;

/**
 * Admin editing of one kind of synchronized entity: a change is saved locally, marked for
 * sending, and that entry alone is sent to Polkurier right away; the full two-way run is left to
 * the cron and the Synchronize button. Deleting is recorded first and carried out by the
 * synchronization, which also deletes the entry in Polkurier.
 *
 * One subclass per entity.
 */
abstract class SyncableEntityHandler implements EntityHandlerInterface
{
    use EditsSyncableEntries;

    public function __construct(
        private readonly SynchronizationInterface $synchronization,
        protected readonly DateTime $dateTime,
        protected readonly SyncLock $syncLock,
    ) {}

    /**
     * @throws NoSuchEntityException
     */
    abstract public function get(int $id): SyncableInterface;

    abstract public function create(): SyncableInterface;

    /**
     * @throws LocalizedException
     */
    abstract protected function store(SyncableInterface $entity): void;

    /**
     * @throws LocalizedException
     */
    abstract protected function remove(SyncableInterface $entity): void;

    /**
     * The kind of entry this handler saves.
     */
    abstract protected function getEntityType(): SyncEntityEnum;

    abstract public function getMenuId(): string;

    abstract public function getLabel(): string;

    abstract public function getPersistorKey(): string;

    /**
     * @param array<string, mixed> $data
     * @throws LocalizedException
     */
    public function save(SyncableInterface $entity, array $data): SyncResult
    {
        return $this->exclusively(function () use ($entity, $data): SyncResult {
            $this->applyFormData($entity, $data);
            $this->store($entity);

            return $this->synchronization->push($this->getEntityType(), $entity);
        });
    }

    /**
     * @throws LocalizedException
     */
    public function delete(SyncableInterface $entity, array $confirmation = []): SyncResult
    {
        return $this->exclusively(function () use ($entity): SyncResult {
            if ($entity->getExtId() === null) {
                // Never sent to Polkurier: nothing to delete there.
                $this->remove($entity);
                $result = new SyncResult($this->getEntityType());
                $result->deleted++;

                return $result;
            }
            $entity->setDeletedAt($this->now())->setSyncState(SyncableInterface::STATE_PENDING);
            $this->store($entity);

            return $this->synchronization->push($this->getEntityType(), $entity);
        });
    }

    /**
     * @return string[]
     * @throws LocalizedException
     */
    public function synchronize(): array
    {
        return $this->synchronization->synchronize([$this->getEntityType()]);
    }
}
