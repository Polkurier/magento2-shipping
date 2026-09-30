<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\SyncEntityEnum;
use Polkurier\Shipping\Model\PackTemplate;
use Polkurier\Shipping\Model\Sync\AdapterInterface;

/**
 * A pack template adapter that only reads, over whatever state a test keeps: $state('remote')
 * and the like answer for it, and $state('save', $entry) records what it does.
 */
class FakeSyncAdapter implements AdapterInterface
{
    /**
     * @param \Closure(string, mixed=): mixed $state
     */
    public function __construct(protected readonly \Closure $state)
    {
    }

    public function getEntityType(): SyncEntityEnum
    {
        return SyncEntityEnum::PackTemplate;
    }

    public function fetchRemote(): array
    {
        return ($this->state)('remote');
    }

    public function fetchLocal(): array
    {
        return \array_values(($this->state)('local'));
    }

    public function createLocal(): SyncableInterface
    {
        return ($this->state)('create');
    }

    public function applyRemote(SyncableInterface $local, array $remote): void
    {
        \assert($local instanceof PackTemplate);
        $local->setExtId((string)$remote['id'])->setName((string)$remote['name']);
    }

    public function getNaturalKey(SyncableInterface|array $entry): ?string
    {
        if (!\is_array($entry)) {
            \assert($entry instanceof PackTemplate);
            $entry = ['name' => $entry->getName()];
        }

        return (string)$entry['name'] ?: null;
    }

    public function save(SyncableInterface $local): void
    {
        ($this->state)('save', $local);
    }

    public function delete(SyncableInterface $local): void
    {
        ($this->state)('delete', $local);
    }
}
