<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Admin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Model\Sync\SyncEngine;

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
     * @param callable(): string[] $change
     * @return string[]
     * @throws LocalizedException
     */
    protected function exclusively(callable $change): array
    {
        return $this->syncLock->run($change);
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
        /** @var AbstractModel&SyncableInterface $entity */
        $entity->addData($data);
        $entity->setUpdatedAt($this->now())->setSyncState(SyncableInterface::STATE_PENDING)->setSyncError(null);
    }

    protected function now(): string
    {
        return \gmdate(SyncEngine::DATE_FORMAT, $this->dateTime->gmtTimestamp());
    }
}
