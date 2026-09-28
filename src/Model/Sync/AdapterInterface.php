<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\SyncEntityEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Everything the synchronization needs to know about one kind of entity.
 *
 * Remote entries are handled as the SDK returns them; each adapter normalizes one into a plain
 * array of the fields both sides share, which is what is compared, fingerprinted and copied.
 */
interface AdapterInterface
{
    public function getEntityType(): SyncEntityEnum;

    /**
     * @return array<string, array<string, mixed>> normalized remote entries, keyed by remote id
     * @throws PolkurierException
     */
    public function fetchRemote(int $storeId): array;

    /**
     * Local entries of a website, including those deleted locally but not yet remotely.
     *
     * @return SyncableInterface[]
     */
    public function fetchLocal(int $websiteId): array;

    public function createLocal(int $websiteId): SyncableInterface;

    /**
     * @param array<string, mixed> $remote
     */
    public function applyRemote(SyncableInterface $local, array $remote): void;

    /**
     * Whether the synchronization may send local changes by itself. When not (Polkurier wants the
     * merchant's confirmation for each), local changes wait as "awaiting confirmation" and are
     * sent from the admin instead.
     */
    public function canPush(): bool;

    /**
     * Creates or updates the entry in Polkurier.
     *
     * @return array<string, mixed> the entry as Polkurier stored it, normalized, with its id
     * @throws PolkurierException
     */
    public function push(SyncableInterface $local, int $storeId): array;

    /**
     * @throws PolkurierException
     */
    public function deleteRemote(SyncableInterface $local, int $storeId): void;

    public function save(SyncableInterface $local): void;

    public function delete(SyncableInterface $local): void;
}
