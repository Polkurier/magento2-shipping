<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime as DateFormat;
use Polkurier\Shipping\Api\Data\SyncableInterface;

/**
 * What every entity handler does the same way: copy a form onto an entry, whose name must be
 * unique. The handlers change entries under {@see \Polkurier\Shipping\Model\Sync\SyncLock}, so that
 * no synchronization saves its older copy over them.
 *
 * Expects $this->dateTime ({@see \Magento\Framework\Stdlib\DateTime\DateTime}).
 */
trait EditsSyncableEntriesTrait
{
    /**
     * Copies the form's fields onto the entity, leaving the synchronization's own fields alone, and
     * marks it as changed in Magento. Its name, which tells it apart in lists and choices, must be
     * unique.
     *
     * @param array<string, mixed> $data
     *
     * @throws LocalizedException when another entry has the name
     */
    protected function applyFormData(SyncableInterface $entity, array $data): void
    {
        unset(
            $data[SyncableInterface::FIELD_EXT_ID],
            $data[SyncableInterface::FIELD_SYNC_STATE],
            $data[SyncableInterface::FIELD_SYNC_ERROR],
            $data[SyncableInterface::FIELD_REMOTE_HASH],
            $data[SyncableInterface::FIELD_REMOTE_UPDATED_AT],
            $data[SyncableInterface::FIELD_SYNCED_AT],
            $data[SyncableInterface::FIELD_DELETED_AT],
            $data[SyncableInterface::FIELD_CREATED_AT],
            $data[SyncableInterface::FIELD_UPDATED_AT],
        );
        if (isset($data[SyncableInterface::FIELD_NAME]) && \is_string($data[SyncableInterface::FIELD_NAME])) {
            $data[SyncableInterface::FIELD_NAME] = \trim($data[SyncableInterface::FIELD_NAME]);
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
