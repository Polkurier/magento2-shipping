<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Polkurier\Shipping\Api\Data\SyncResultInterface;
use Polkurier\Shipping\Enum\SyncEntityEnum;

/**
 * What one synchronization run of one entity type did. The engine and the entry handlers count
 * into its fields as they go; what they hand on is read through SyncResultInterface.
 */
class SyncResult implements SyncResultInterface
{
    public int $created = 0;
    public int $pulled = 0;
    public int $pushed = 0;
    public int $deleted = 0;
    public int $unchanged = 0;
    public int $unconfirmed = 0;
    public int $failed = 0;

    /**
     * @var string[]
     */
    public array $errors = [];

    public function __construct(
        public readonly SyncEntityEnum $entityType,
    ) {}

    public function getEntityType(): SyncEntityEnum
    {
        return $this->entityType;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getPulled(): int
    {
        return $this->pulled;
    }

    public function getPushed(): int
    {
        return $this->pushed;
    }

    public function getDeleted(): int
    {
        return $this->deleted;
    }

    public function getUnchanged(): int
    {
        return $this->unchanged;
    }

    public function getUnconfirmed(): int
    {
        return $this->unconfirmed;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Adds what another run did, as for the entries of a mass action pushed one by one.
     */
    public function add(SyncResultInterface $other): self
    {
        $this->created += $other->getCreated();
        $this->pulled += $other->getPulled();
        $this->pushed += $other->getPushed();
        $this->deleted += $other->getDeleted();
        $this->unchanged += $other->getUnchanged();
        $this->unconfirmed += $other->getUnconfirmed();
        $this->failed += $other->getFailed();
        $this->errors = \array_merge($this->errors, $other->getErrors());

        return $this;
    }

    public function summary(): string
    {
        return (string)\__(
            '%1: %2 new from Polkurier, %3 updated from Polkurier, %4 sent to Polkurier, %5 removed, %6 unchanged, %7 awaiting confirmation, %8 failed.',
            $this->entityType->label(),
            $this->created,
            $this->pulled,
            $this->pushed,
            $this->deleted,
            $this->unchanged,
            $this->unconfirmed,
            $this->failed,
        );
    }
}
