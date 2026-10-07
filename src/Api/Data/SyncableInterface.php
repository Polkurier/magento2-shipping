<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * An entity kept both in Magento and in the Polkurier account, and synchronized between them.
 *
 * @api
 */
interface SyncableInterface
{
    public const string FIELD_EXT_ID = 'ext_id';

    /**
     * What the merchant tells the entries apart by.
     */
    public const string FIELD_NAME = 'name';

    public const string FIELD_SYNC_STATE = 'sync_state';
    public const string FIELD_SYNC_ERROR = 'sync_error';
    public const string FIELD_REMOTE_HASH = 'remote_hash';
    public const string FIELD_REMOTE_UPDATED_AT = 'remote_updated_at';
    public const string FIELD_SYNCED_AT = 'synced_at';
    public const string FIELD_DELETED_AT = 'deleted_at';
    public const string FIELD_CREATED_AT = 'created_at';
    public const string FIELD_UPDATED_AT = 'updated_at';

    /**
     * Changed in Magento and not yet sent, or never sent.
     */
    public const string STATE_PENDING = 'pending';

    /**
     * Equal on both sides as of the last synchronization.
     */
    public const string STATE_SYNCED = 'synced';

    /**
     * The last attempt to send it failed; see the sync error.
     */
    public const string STATE_ERROR = 'error';

    /**
     * Changed in Magento, but Polkurier only takes the change with the merchant's confirmation
     * (a PIN or two-factor code), which a background synchronization cannot give.
     */
    public const string STATE_UNCONFIRMED = 'unconfirmed';

    /**
     * @return int|null
     */
    public function getId();

    /**
     * Id of the entry in the Polkurier account; null until it has been sent there.
     *
     * @return string|null
     */
    public function getExtId(): ?string;

    /**
     * @param string|null $extId
     *
     * @return $this
     */
    public function setExtId(?string $extId): static;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName(string $name): static;

    /**
     * @return string
     */
    public function getSyncState(): string;

    /**
     * @param string $syncState
     *
     * @return $this
     */
    public function setSyncState(string $syncState): static;

    /**
     * @return string|null
     */
    public function getSyncError(): ?string;

    /**
     * @param string|null $syncError
     *
     * @return $this
     */
    public function setSyncError(?string $syncError): static;

    /**
     * Fingerprint of the remote entry as last seen, to notice remote changes.
     *
     * @return string|null
     */
    public function getRemoteHash(): ?string;

    /**
     * @param string|null $remoteHash
     *
     * @return $this
     */
    public function setRemoteHash(?string $remoteHash): static;

    /**
     * When the remote entry last changed, as far as is known.
     *
     * @return string|null
     */
    public function getRemoteUpdatedAt(): ?string;

    /**
     * @param string|null $remoteUpdatedAt
     *
     * @return $this
     */
    public function setRemoteUpdatedAt(?string $remoteUpdatedAt): static;

    /**
     * @return string|null
     */
    public function getSyncedAt(): ?string;

    /**
     * @param string|null $syncedAt
     *
     * @return $this
     */
    public function setSyncedAt(?string $syncedAt): static;

    /**
     * Set when the entry was deleted in Magento but the deletion has not reached Polkurier yet.
     *
     * @return string|null
     */
    public function getDeletedAt(): ?string;

    /**
     * @param string|null $deletedAt
     *
     * @return $this
     */
    public function setDeletedAt(?string $deletedAt): static;

    /**
     * When the entry was first saved in Magento.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string|null $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(?string $createdAt): static;

    /**
     * When the entry was last changed in Magento.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * @param string|null $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt(?string $updatedAt): static;
}
