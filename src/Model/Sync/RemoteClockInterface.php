<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Polkurier\Shipping\Api\Data\SyncableInterface;

/**
 * Tells when a remote entry last changed.
 *
 * The API does not report modification times yet; it is due to. Until it does, the implementation
 * infers it ({@see DetectedChangeClock}). Once responses carry an updatedAt, an implementation
 * reading it replaces that one in di.xml and nothing else changes.
 */
interface RemoteClockInterface
{
    /**
     * @param array<string, mixed> $remote the remote entry, normalized by its adapter
     * @param string $remoteHash fingerprint of $remote
     */
    public function getModifiedAt(array $remote, string $remoteHash, SyncableInterface $local, \DateTimeImmutable $now): \DateTimeImmutable;
}
