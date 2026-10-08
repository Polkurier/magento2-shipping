<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Polkurier\Shipping\Api\Data\SyncableInterface;

/**
 * The synchronization bookkeeping shared by entities mirrored with the Polkurier account.
 *
 * @see SyncableInterface
 */
trait SyncableTrait
{
    public function getExtId(): ?string
    {
        return $this->nullableString(SyncableInterface::FIELD_EXT_ID);
    }

    public function setExtId(?string $extId): static
    {
        return $this->setData(SyncableInterface::FIELD_EXT_ID, $extId);
    }

    public function getSyncState(): string
    {
        return (string)($this->getData(SyncableInterface::FIELD_SYNC_STATE) ?: SyncableInterface::STATE_PENDING);
    }

    public function setSyncState(string $syncState): static
    {
        return $this->setData(SyncableInterface::FIELD_SYNC_STATE, $syncState);
    }

    public function getSyncError(): ?string
    {
        return $this->nullableString(SyncableInterface::FIELD_SYNC_ERROR);
    }

    public function setSyncError(?string $syncError): static
    {
        return $this->setData(SyncableInterface::FIELD_SYNC_ERROR, $syncError);
    }

    public function getRemoteHash(): ?string
    {
        return $this->nullableString(SyncableInterface::FIELD_REMOTE_HASH);
    }

    public function setRemoteHash(?string $remoteHash): static
    {
        return $this->setData(SyncableInterface::FIELD_REMOTE_HASH, $remoteHash);
    }

    public function getRemoteUpdatedAt(): ?string
    {
        return $this->nullableString(SyncableInterface::FIELD_REMOTE_UPDATED_AT);
    }

    public function setRemoteUpdatedAt(?string $remoteUpdatedAt): static
    {
        return $this->setData(SyncableInterface::FIELD_REMOTE_UPDATED_AT, $remoteUpdatedAt);
    }

    public function getSyncedAt(): ?string
    {
        return $this->nullableString(SyncableInterface::FIELD_SYNCED_AT);
    }

    public function setSyncedAt(?string $syncedAt): static
    {
        return $this->setData(SyncableInterface::FIELD_SYNCED_AT, $syncedAt);
    }

    public function getDeletedAt(): ?string
    {
        return $this->nullableString(SyncableInterface::FIELD_DELETED_AT);
    }

    public function setDeletedAt(?string $deletedAt): static
    {
        return $this->setData(SyncableInterface::FIELD_DELETED_AT, $deletedAt);
    }

    public function getCreatedAt(): ?string
    {
        return $this->nullableString(SyncableInterface::FIELD_CREATED_AT);
    }

    public function setCreatedAt(?string $createdAt): static
    {
        return $this->setData(SyncableInterface::FIELD_CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->nullableString(SyncableInterface::FIELD_UPDATED_AT);
    }

    public function setUpdatedAt(?string $updatedAt): static
    {
        return $this->setData(SyncableInterface::FIELD_UPDATED_AT, $updatedAt);
    }

    private function nullableString(string $field): ?string
    {
        $value = $this->getData($field);

        return $value === null || $value === '' ? null : (string)$value;
    }
}
