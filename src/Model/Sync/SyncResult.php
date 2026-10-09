<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Polkurier\Shipping\Enum\Sync\SyncEntityEnum;

/**
 * What one synchronization run of one entity type did, as counts. The engine and the entry handlers
 * add to its fields as they go, and callers read the results from them.
 */
class SyncResult
{
    /**
     * Entries new in Polkurier, added in Magento.
     */
    public int $created = 0;

    /**
     * Entries changed in Polkurier, updated in Magento.
     */
    public int $pulled = 0;

    /**
     * Entries changed in Magento, sent to Polkurier.
     */
    public int $pushed = 0;

    /**
     * Entries removed on either side, as the other side deleted them.
     */
    public int $deleted = 0;

    public int $unchanged = 0;

    /**
     * Entries saved in Magento that Polkurier takes only once the merchant confirms them.
     */
    public int $unconfirmed = 0;

    public int $failed = 0;

    /**
     * @var string[] why those failed
     */
    public array $errors = [];

    public function __construct(
        public readonly SyncEntityEnum $entityType,
    ) {}

    public function summary(): string
    {
        return (string)__(
            '%1: %2 new from Polkurier, %3 updated from Polkurier, %4 sent to Polkurier, %5 removed, %6 unchanged, '
            . '%7 awaiting confirmation, %8 failed.',
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
