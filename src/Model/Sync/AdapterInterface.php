<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Enum\Sync\SyncEntityEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Everything the synchronization needs to know about one kind of entity.
 *
 * Remote entries are handled as the SDK returns them; each adapter normalizes one into a plain
 * array of the fields both sides share, which is what is compared, fingerprinted and copied.
 *
 * An adapter whose local changes the synchronization may send by itself also implements
 * {@see \Polkurier\Shipping\Model\Sync\PushableAdapterInterface}. The others (Polkurier wants the
 * merchant's confirmation for each change) only read: their local changes wait as "awaiting
 * confirmation" and are sent from the admin instead.
 */
interface AdapterInterface
{
    public function getEntityType(): SyncEntityEnum;

    /**
     * @return array<string, array<string, mixed>> normalized remote entries, keyed by remote id
     * @throws PolkurierException
     */
    public function fetchRemote(): array;

    /**
     * Local entries, including those deleted locally but not yet remotely.
     *
     * @return SyncableInterface[]
     */
    public function fetchLocal(): array;

    public function createLocal(): SyncableInterface;

    /**
     * @param array<string, mixed> $remote
     */
    public function applyRemote(SyncableInterface $local, array $remote): void;

    /**
     * What identifies an entry besides its remote id, such as its name: an entry sent to Polkurier
     * whose link failed to be saved in Magento is recognised by it, instead of being sent again.
     *
     * @param SyncableInterface|array<string, mixed> $entry a local entry, or a normalized remote one
     *
     * @return string|null null when nothing but the id tells entries apart
     */
    public function getNaturalKey(SyncableInterface|array $entry): ?string;

    public function save(SyncableInterface $local): void;

    public function delete(SyncableInterface $local): void;
}
