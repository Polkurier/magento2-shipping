<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Admin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime as DateFormat;
use Polkurier\Shipping\Api\Data\SyncableInterface;

/**
 * What every entity handler does alike: copy a form onto an entry, and change entries while no
 * synchronization is running.
 *
 * Expects $this->dateTime (Magento\Framework\Stdlib\DateTime\DateTime) and $this->syncLock
 * (Polkurier\Shipping\Model\Sync\SyncLock).
 */
trait EditsSyncableEntries
{
    /**
     * Runs a change of the entries while no synchronization is running, so that none saves its
     * older copy of the entry over it.
     *
     * @template T
     *
     * @param callable(): T $change
     *
     * @return T
     * @throws LocalizedException
     */
    protected function exclusively(callable $change): mixed
    {
        return $this->syncLock->run($change);
    }

    /**
     * Copies the form's fields onto the entity, leaving the synchronization's own fields alone,
     * and marks it as changed in Magento. Its name, which tells it apart in the lists and
     * choices, must be its own.
     *
     * @param array<string, mixed> $data
     *
     * @throws LocalizedException when another entry has the name
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
        if (isset($data['name']) && \is_string($data['name'])) {
            $data['name'] = \trim($data['name']);
        }
        /** @var AbstractModel&SyncableInterface $entity */
        $entity->addData($data);
        $this->assertUniqueName($entity);
        $entity->setUpdatedAt($this->now())->setSyncState(SyncableInterface::STATE_PENDING)->setSyncError(null);
    }

    /**
     * @throws LocalizedException when another entry, not deleted, has the entity's name
     */
    abstract protected function assertUniqueName(SyncableInterface $entity): void;

    protected function now(): string
    {
        return \gmdate(DateFormat::DATETIME_PHP_FORMAT, $this->dateTime->gmtTimestamp());
    }
}
