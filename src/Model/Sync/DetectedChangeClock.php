<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Polkurier\Shipping\Api\Data\SyncableInterface;

/**
 * Stand-in until the API reports modification times: a remote entry is taken to have changed when
 * a synchronization first sees it different, and otherwise at the time last recorded for it.
 *
 * So when both sides changed between two synchronizations, the remote change counts as the later
 * one. Local edits are sent to Polkurier as soon as they are saved, which keeps that case rare.
 */
class DetectedChangeClock implements RemoteClockInterface
{
    public function getModifiedAt(array $remote, string $remoteHash, SyncableInterface $local, \DateTimeImmutable $now): \DateTimeImmutable
    {
        if ($local->getRemoteHash() !== $remoteHash) {
            return $now;
        }
        $known = $local->getRemoteUpdatedAt() ?? $local->getSyncedAt();

        return $known !== null ? new \DateTimeImmutable($known) : new \DateTimeImmutable('@0');
    }
}
