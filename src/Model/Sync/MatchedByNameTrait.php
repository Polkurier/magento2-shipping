<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Polkurier\Shipping\Api\Data\SyncableInterface;

/**
 * Entries without an id on the other side are paired by their name, whatever its case and the
 * spaces around it.
 */
trait MatchedByNameTrait
{
    public function getNaturalKey(SyncableInterface|array $entry): ?string
    {
        $value = \is_array($entry) ? (string)($entry[SyncableInterface::FIELD_NAME] ?? '') : $entry->getName();
        $key = (string)\mb_strtolower(\trim($value));

        return $key !== '' ? $key : null;
    }
}
