<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Polkurier\Shipping\Api\SyncEntityEnum;

/**
 * What one synchronization run of one entity type did.
 */
class SyncResult
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
