<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

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
        $value = $this->getData(SyncableInterface::EXT_ID);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setExtId(?string $extId): static
    {
        return $this->setData(SyncableInterface::EXT_ID, $extId);
    }

    public function getSyncState(): string
    {
        return (string)($this->getData(SyncableInterface::SYNC_STATE) ?: SyncableInterface::STATE_PENDING);
    }

    public function setSyncState(string $syncState): static
    {
        return $this->setData(SyncableInterface::SYNC_STATE, $syncState);
    }

    public function getSyncError(): ?string
    {
        $value = $this->getData(SyncableInterface::SYNC_ERROR);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setSyncError(?string $syncError): static
    {
        return $this->setData(SyncableInterface::SYNC_ERROR, $syncError);
    }

    public function getRemoteHash(): ?string
    {
        $value = $this->getData(SyncableInterface::REMOTE_HASH);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRemoteHash(?string $remoteHash): static
    {
        return $this->setData(SyncableInterface::REMOTE_HASH, $remoteHash);
    }

    public function getRemoteUpdatedAt(): ?string
    {
        $value = $this->getData(SyncableInterface::REMOTE_UPDATED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRemoteUpdatedAt(?string $remoteUpdatedAt): static
    {
        return $this->setData(SyncableInterface::REMOTE_UPDATED_AT, $remoteUpdatedAt);
    }

    public function getSyncedAt(): ?string
    {
        $value = $this->getData(SyncableInterface::SYNCED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setSyncedAt(?string $syncedAt): static
    {
        return $this->setData(SyncableInterface::SYNCED_AT, $syncedAt);
    }

    public function getDeletedAt(): ?string
    {
        $value = $this->getData(SyncableInterface::DELETED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setDeletedAt(?string $deletedAt): static
    {
        return $this->setData(SyncableInterface::DELETED_AT, $deletedAt);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData(SyncableInterface::UPDATED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setUpdatedAt(?string $updatedAt): static
    {
        return $this->setData(SyncableInterface::UPDATED_AT, $updatedAt);
    }
}
