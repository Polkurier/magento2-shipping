<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

/**
 * Brings the address book, bank accounts and pack templates kept in Magento level with the
 * Polkurier account. The later change wins.
 */
interface SynchronizationInterface
{
    /**
     * @param \Polkurier\Shipping\Api\SyncEntityEnum[] $entities the kinds to synchronize; empty means all
     * @return string[] a human-readable summary line per kind
     */
    public function synchronize(array $entities = []): array;

    /**
     * Sends one entry just changed in Magento, and nothing else: no other entry is read or changed
     * on either side.
     *
     * @param \Polkurier\Shipping\Api\SyncEntityEnum $kind
     * @param \Polkurier\Shipping\Api\Data\SyncableInterface $entry
     * @return string[] a human-readable summary line
     */
    public function push(SyncEntityEnum $kind, Data\SyncableInterface $entry): array;
}
