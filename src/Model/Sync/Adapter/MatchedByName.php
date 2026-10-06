<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync\Adapter;

use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;

/**
 * Entries without an id on the other side are paired by their name, whatever its case and the
 * spaces around it.
 */
trait MatchedByName
{
    public function getNaturalKey(SyncableInterface|array $entry): ?string
    {
        /** @var AddressInterface|PackTemplateInterface|array<string, mixed> $entry */
        $value = \is_array($entry) ? (string)($entry['name'] ?? '') : $entry->getName();
        $key = (string)\mb_strtolower(\trim($value));

        return $key !== '' ? $key : null;
    }
}
