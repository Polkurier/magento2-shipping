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
use Polkurier\Shipping\Model\Sync\SyncEngine;

/**
 * Admin editing of one kind of synchronized entity: a change is saved locally, marked for
 * sending, and sent to Polkurier right away. Deleting is recorded first and carried out by the
 * synchronization, which also deletes the entry in Polkurier.
 *
 * One subclass per entity.
 */
abstract class SyncableEntityHandler
{
    public function __construct(
        private readonly SynchronizationInterface $synchronization,
        private readonly DateTime $dateTime,
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

    abstract public function getAclResource(): string;

    abstract public function getLabel(): string;

    abstract public function getPersistorKey(): string;

    /**
     * @param array<string, mixed> $data
     * @return string[] what the synchronization reported
     * @throws LocalizedException
     */
    public function save(SyncableInterface $entity, array $data): array
    {
        $this->applyFormData($entity, $data);
        $this->store($entity);

        return $this->synchronization->synchronize($entity->getWebsiteId(), [$this->getEntityType()]);
    }

    /**
     * Copies the form's fields onto the entity, leaving the synchronization's own fields alone,
     * and marks it as changed in Magento.
     *
     * @param array<string, mixed> $data
     */
    protected function applyFormData(SyncableInterface $entity, array $data): void
    {
        unset(
            $data[SyncableInterface::EXT_ID],
            $data[SyncableInterface::SYNC_STATE],
            $data[SyncableInterface::SYNC_ERROR],
            $data[SyncableInterface::REMOTE_HASH],
            $data[SyncableInterface::REMOTE_UPDATED_AT],
            $data[SyncableInterface::SYNCED_AT],
            $data[SyncableInterface::DELETED_AT],
            $data[SyncableInterface::CREATED_AT],
            $data[SyncableInterface::UPDATED_AT],
        );
        /** @var \Magento\Framework\Model\AbstractModel&SyncableInterface $entity */
        $entity->addData($data);
        $entity->setUpdatedAt($this->now())->setSyncState(SyncableInterface::STATE_PENDING)->setSyncError(null);
    }

    /**
     * @return string[] what the synchronization reported
     * @throws LocalizedException
     */
    public function delete(SyncableInterface $entity): array
    {
        if ($entity->getExtId() === null) {
            $this->remove($entity);

            return [];
        }
        $entity->setDeletedAt($this->now())->setSyncState(SyncableInterface::STATE_PENDING);
        $this->store($entity);

        return $this->synchronization->synchronize($entity->getWebsiteId(), [$this->getEntityType()]);
    }

    /**
     * @return string[]
     * @throws LocalizedException
     */
    public function synchronize(int $websiteId): array
    {
        return $this->synchronization->synchronize($websiteId, [$this->getEntityType()]);
    }


    private function now(): string
    {
        return \gmdate(SyncEngine::DATE_FORMAT, $this->dateTime->gmtTimestamp());
    }
}
