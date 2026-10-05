<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Api\Data\SyncableInterface;

/**
 * Synchronization states.
 */
readonly class SyncState extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            SyncableInterface::STATE_SYNCED => (string)\__('In sync'),
            SyncableInterface::STATE_PENDING => (string)\__('Waiting to be sent'),
            SyncableInterface::STATE_ERROR => (string)\__('Error'),
            SyncableInterface::STATE_UNCONFIRMED => (string)\__('Awaiting confirmation'),
        ];
    }
}
