<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * An adapter whose local changes the synchronization sends to Polkurier by itself.
 */
interface PushableAdapterInterface extends AdapterInterface
{
    /**
     * Creates or updates the entry in Polkurier.
     *
     * @return array<string, mixed> the entry as Polkurier stored it, normalized, with its id
     * @throws PolkurierException
     */
    public function push(SyncableInterface $local): array;

    /**
     * @throws PolkurierException
     */
    public function deleteRemote(SyncableInterface $local): void;
}
