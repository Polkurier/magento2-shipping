<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

/**
 * Brings the address book, bank accounts and pack templates kept in Magento level with the
 * Polkurier account of one website. The later change wins.
 */
interface SynchronizationInterface
{
    /**
     * @param int $websiteId
     * @param \Polkurier\Shipping\Api\SyncEntityEnum[] $entities the kinds to synchronize; empty means all
     * @return string[] a human-readable summary line per kind
     */
    public function synchronize(int $websiteId, array $entities = []): array;
}
