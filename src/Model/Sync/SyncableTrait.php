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
        $value = $this->getData(SyncableInterface::FIELD_EXT_ID);

        return $value === null || $value === '' ? null : (string)$value;
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
        $value = $this->getData(SyncableInterface::FIELD_SYNC_ERROR);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setSyncError(?string $syncError): static
    {
        return $this->setData(SyncableInterface::FIELD_SYNC_ERROR, $syncError);
    }

    public function getRemoteHash(): ?string
    {
        $value = $this->getData(SyncableInterface::FIELD_REMOTE_HASH);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRemoteHash(?string $remoteHash): static
    {
        return $this->setData(SyncableInterface::FIELD_REMOTE_HASH, $remoteHash);
    }

    public function getRemoteUpdatedAt(): ?string
    {
        $value = $this->getData(SyncableInterface::FIELD_REMOTE_UPDATED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRemoteUpdatedAt(?string $remoteUpdatedAt): static
    {
        return $this->setData(SyncableInterface::FIELD_REMOTE_UPDATED_AT, $remoteUpdatedAt);
    }

    public function getSyncedAt(): ?string
    {
        $value = $this->getData(SyncableInterface::FIELD_SYNCED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setSyncedAt(?string $syncedAt): static
    {
        return $this->setData(SyncableInterface::FIELD_SYNCED_AT, $syncedAt);
    }

    public function getDeletedAt(): ?string
    {
        $value = $this->getData(SyncableInterface::FIELD_DELETED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setDeletedAt(?string $deletedAt): static
    {
        return $this->setData(SyncableInterface::FIELD_DELETED_AT, $deletedAt);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData(SyncableInterface::FIELD_CREATED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setCreatedAt(?string $createdAt): static
    {
        return $this->setData(SyncableInterface::FIELD_CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData(SyncableInterface::FIELD_UPDATED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setUpdatedAt(?string $updatedAt): static
    {
        return $this->setData(SyncableInterface::FIELD_UPDATED_AT, $updatedAt);
    }
}
