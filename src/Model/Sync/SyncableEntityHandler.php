<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Enum\FormMemoryEnum;
use Polkurier\Shipping\Enum\SyncEntityEnum;

/**
 * Admin editing of one kind of synchronized entity: a change is saved locally, marked for
 * sending, and that entry alone is sent to Polkurier right away; the full two-way run is left to
 * the cron and the Synchronize button. Deleting is recorded first and carried out by the
 * synchronization, which also deletes the entry in Polkurier.
 *
 * One subclass per entity.
 */
abstract readonly class SyncableEntityHandler implements EntityHandlerInterface
{
    use EditsSyncableEntriesTrait;

    public function __construct(
        private Synchronization $synchronization,
        protected DateTime $dateTime,
        protected SyncLock $syncLock,
        private SyncResultFactory $syncResultFactory,
    ) {}

    /**
     * @throws NoSuchEntityException
     */
    abstract public function get(int $id): SyncableInterface;

    abstract public function create(): SyncableInterface;

    abstract public function getMenuId(): string;

    abstract public function getLabel(): string;

    abstract public function getFormMemory(): FormMemoryEnum;

    /**
     * @param array<string, mixed> $data
     *
     * @throws LocalizedException
     */
    public function save(SyncableInterface $entity, array $data): SyncResult
    {
        return $this->syncLock->run(function () use ($entity, $data): SyncResult {
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
        return $this->syncLock->run(function () use ($entity): SyncResult {
            if ($entity->getExtId() === null) {
                // Never sent to Polkurier: nothing to delete there.
                $this->remove($entity);
                $result = $this->syncResultFactory->create(['entityType' => $this->getEntityType()]);
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
}
