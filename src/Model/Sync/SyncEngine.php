<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Psr\Log\LoggerInterface;

/**
 * Two-way synchronization of one kind of entity between Magento and a Polkurier account.
 *
 * For each pair, a side counts as changed when it differs from how the pair was left by the last
 * synchronization: locally when edited or deleted since, remotely when its fingerprint moved. A
 * change on one side only is copied to the other. When both changed, the later change wins, by
 * the local updated_at and the {@see RemoteClockInterface}. An entry deleted remotely is deleted
 * locally, unless it was edited locally since the last synchronization, in which case it is sent
 * again. New remote entries are created locally; new local ones are sent.
 */
readonly class SyncEngine
{
    public const string DATE_FORMAT = 'Y-m-d H:i:s';

    private const array LOCAL_CHANGE_STATES = [
        SyncableInterface::STATE_PENDING,
        SyncableInterface::STATE_ERROR,
        SyncableInterface::STATE_UNCONFIRMED,
    ];

    public function __construct(
        private RemoteClockInterface $clock,
        private ErrorMessages $errorMessages,
        private DateTime $dateTime,
        private LoggerInterface $logger,
    ) {}

    /**
     * Synchronizes every entry of the kind, both ways, or only the one given: then only that entry
     * is sent, pulled or deleted, as the full run would, and nothing else changes on either side.
     *
     * @param SyncableInterface|null $only one local entry, e.g. just saved in the admin
     * @throws PolkurierException when the remote list cannot be read; nothing is changed then
     */
    public function run(AdapterInterface $adapter, ?SyncableInterface $only = null): SyncResult
    {
        $result = new SyncResult($adapter->getEntityType());
        $now = new \DateTimeImmutable('@' . $this->dateTime->gmtTimestamp());
        $remote = $adapter->fetchRemote();
        $locals = $adapter->fetchLocal();
        $unclaimed = $this->unclaimedByKey($adapter, $remote, $locals);
        if ($only !== null) {
            $locals = [$only];
        }

        foreach ($locals as $local) {
            $extId = $local->getExtId();
            try {
                if ($extId === null && $local->getDeletedAt() === null) {
                    // Sent before, but its link wasn't saved: linked now rather than sent again.
                    $key = $adapter->getNaturalKey($local);
                    $ids = $key !== null ? ($unclaimed[$key] ?? []) : [];
                    if (\count($ids) === 1) {
                        $extId = (string)$ids[0];
                        unset($unclaimed[$key]);
                        $local->setExtId($extId);
                    }
                }
                if ($extId === null) {
                    $this->handleUnlinked($adapter, $local, $now, $result);
                } elseif (!isset($remote[$extId])) {
                    $this->handleRemoteDeleted($adapter, $local, $now, $result);
                } else {
                    $this->handlePair($adapter, $local, $remote[$extId], $now, $result);
                }
            } catch (\Throwable $e) {
                // One entry that fails, for whatever reason, doesn't stop the others.
                $this->markError($adapter, $local, $e, $result);
            }
            if ($extId !== null) {
                unset($remote[$extId]);
            }
        }

        if ($only !== null) {
            return $result;
        }
        foreach ($remote as $extId => $entry) {
            try {
                $local = $adapter->createLocal();
                $this->pulled($adapter, $local, $entry, $this->clock->getModifiedAt($entry, $this->hash($entry), $local, $now), $now);
                $result->created++;
            } catch (\Throwable $e) {
                $message = $this->errorMessages->describe($e);
                $this->logger->warning('Polkurier synchronization failed', [
                    'entity' => $adapter->getEntityType()->value,
                    'remote' => (string)$extId,
                    'error' => $message,
                ]);
                $result->failed++;
                $result->errors[] = $message;
            }
        }

        return $result;
    }

    /**
     * Remote entries no local entry is linked to, by natural key.
     *
     * @param array<string, array<string, mixed>> $remote
     * @param SyncableInterface[] $locals
     * @return array<string, array<int, string|int>> remote ids by key
     */
    private function unclaimedByKey(AdapterInterface $adapter, array $remote, array $locals): array
    {
        $linked = [];
        foreach ($locals as $local) {
            if ($local->getExtId() !== null) {
                $linked[$local->getExtId()] = true;
            }
        }
        $byKey = [];
        foreach ($remote as $id => $entry) {
            $key = isset($linked[(string)$id]) ? null : $adapter->getNaturalKey($entry);
            if ($key !== null) {
                $byKey[$key][] = $id;
            }
        }

        return $byKey;
    }

    /**
     * @param array<string, mixed> $remote
     */
    public function hash(array $remote): string
    {
        \ksort($remote);

        return \sha1((string)\json_encode($remote, \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION));
    }

    private function handleUnlinked(AdapterInterface $adapter, SyncableInterface $local, \DateTimeImmutable $now, SyncResult $result): void
    {
        if ($local->getDeletedAt() !== null) {
            $adapter->delete($local);
            $result->deleted++;

            return;
        }
        if (!$adapter instanceof PushableAdapterInterface) {
            $this->awaitConfirmation($adapter, $local, $result);

            return;
        }
        $this->pushed($adapter, $local, $adapter->push($local), $now);
        $result->pushed++;
    }

    private function handleRemoteDeleted(AdapterInterface $adapter, SyncableInterface $local, \DateTimeImmutable $now, SyncResult $result): void
    {
        if ($local->getDeletedAt() === null && $this->isLocallyChanged($local)) {
            $local->setExtId(null);
            if ($adapter instanceof PushableAdapterInterface) {
                $this->pushed($adapter, $local, $adapter->push($local), $now);
                $result->pushed++;
            } else {
                $this->awaitConfirmation($adapter, $local, $result);
            }

            return;
        }
        $adapter->delete($local);
        $result->deleted++;
    }

    /**
     * @param array<string, mixed> $remote
     */
    private function handlePair(AdapterInterface $adapter, SyncableInterface $local, array $remote, \DateTimeImmutable $now, SyncResult $result): void
    {
        $hash = $this->hash($remote);
        $remoteChanged = $hash !== $local->getRemoteHash();
        $localChanged = $this->isLocallyChanged($local);
        $remoteAt = $this->clock->getModifiedAt($remote, $hash, $local, $now);

        if ($local->getDeletedAt() !== null) {
            if ($remoteChanged && $remoteAt > new \DateTimeImmutable($local->getDeletedAt())) {
                $local->setDeletedAt(null);
                $this->pulled($adapter, $local, $remote, $remoteAt, $now);
                $result->pulled++;
            } elseif ($adapter instanceof PushableAdapterInterface) {
                $adapter->deleteRemote($local);
                $adapter->delete($local);
                $result->deleted++;
            } else {
                // Deleting only the Magento copy would bring it back with the next pull.
                $this->awaitConfirmation($adapter, $local, $result);
            }

            return;
        }

        $pull = match (true) {
            $localChanged && $remoteChanged => $remoteAt > $this->localChangedAt($local),
            $remoteChanged => true,
            default => false,
        };
        if ($pull) {
            $this->pulled($adapter, $local, $remote, $remoteAt, $now);
            $result->pulled++;
        } elseif ($localChanged && $adapter instanceof PushableAdapterInterface) {
            $this->pushed($adapter, $local, $adapter->push($local), $now);
            $result->pushed++;
        } elseif ($localChanged) {
            $this->awaitConfirmation($adapter, $local, $result);
        } else {
            if ($local->getSyncState() !== SyncableInterface::STATE_SYNCED) {
                $local->setSyncState(SyncableInterface::STATE_SYNCED)->setSyncError(null);
                $adapter->save($local);
            }
            $result->unchanged++;
        }
    }

    /**
     * Keeps a change Polkurier only accepts with the merchant's confirmation, until it is given.
     */
    private function awaitConfirmation(AdapterInterface $adapter, SyncableInterface $local, SyncResult $result): void
    {
        if ($local->getSyncState() !== SyncableInterface::STATE_UNCONFIRMED) {
            $local->setSyncState(SyncableInterface::STATE_UNCONFIRMED)->setSyncError(null);
            $adapter->save($local);
        }
        $result->unconfirmed++;
    }

    /**
     * @param array<string, mixed> $remote
     */
    private function pulled(AdapterInterface $adapter, SyncableInterface $local, array $remote, \DateTimeImmutable $remoteAt, \DateTimeImmutable $now): void
    {
        $adapter->applyRemote($local, $remote);
        $stamp = $now->format(self::DATE_FORMAT);
        $local->setRemoteHash($this->hash($remote))
            ->setRemoteUpdatedAt($remoteAt->format(self::DATE_FORMAT))
            ->setSyncedAt($stamp)
            ->setUpdatedAt($stamp)
            ->setSyncState(SyncableInterface::STATE_SYNCED)
            ->setSyncError(null);
        $adapter->save($local);
    }

    /**
     * Records an entry as sent: also for pushes the adapter cannot make by itself, such as a bank
     * account saved with the merchant's confirmation.
     *
     * @param array<string, mixed> $stored the entry as Polkurier stored it, normalized
     */
    public function pushed(AdapterInterface $adapter, SyncableInterface $local, array $stored, \DateTimeImmutable $now): void
    {
        $stamp = $now->format(self::DATE_FORMAT);
        $local->setExtId(isset($stored['id']) ? (string)$stored['id'] : $local->getExtId())
            ->setRemoteHash($this->hash($stored))
            ->setRemoteUpdatedAt($stamp)
            ->setSyncedAt($stamp)
            ->setUpdatedAt($stamp)
            ->setSyncState(SyncableInterface::STATE_SYNCED)
            ->setSyncError(null);
        try {
            $adapter->save($local);
        } catch (\Throwable $e) {
            // Polkurier has it now: the next run links it by its natural key, and the log says which it is.
            $this->logger->error('Polkurier entry sent but its link not saved', [
                'entity' => $adapter->getEntityType()->value,
                'id' => $local->getId(),
                'remote' => $local->getExtId(),
            ]);

            throw $e;
        }
    }

    private function markError(AdapterInterface $adapter, SyncableInterface $local, \Throwable $e, SyncResult $result): void
    {
        $message = $this->errorMessages->describe($e);
        $this->logger->warning('Polkurier synchronization failed', [
            'entity' => $adapter->getEntityType()->value,
            'id' => $local->getId(),
            'error' => $message,
        ]);
        $local->setSyncState(SyncableInterface::STATE_ERROR)->setSyncError($message);
        $adapter->save($local);
        $result->failed++;
        $result->errors[] = $message;
    }

    /**
     * Saving in Magento marks an entry pending, and a change that didn't get through stays in
     * error or waits for confirmation: those are changed whatever the times say. The times have
     * a resolution of a second, the same second as the last synchronization for an entry edited
     * right after it.
     */
    private function isLocallyChanged(SyncableInterface $local): bool
    {
        if ($local->getSyncedAt() === null || \in_array($local->getSyncState(), self::LOCAL_CHANGE_STATES, true)) {
            return true;
        }

        return $this->localChangedAt($local) > new \DateTimeImmutable($local->getSyncedAt());
    }

    private function localChangedAt(SyncableInterface $local): \DateTimeImmutable
    {
        return new \DateTimeImmutable($local->getDeletedAt() ?? $local->getUpdatedAt() ?? '@0');
    }
}
