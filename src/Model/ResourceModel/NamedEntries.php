<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel;

use Polkurier\Shipping\Api\Data\SyncableInterface;

/**
 * Entries told apart by their name in the admin's lists and choices: addresses, bank accounts
 * and pack templates. For the resource models of their tables, which have a "name" column.
 */
trait NamedEntries
{
    /**
     * Whether another entry, not deleted, has the name, regardless of case and surrounding spaces.
     */
    public function isNameTaken(string $name, ?int $exceptId = null): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [$this->getIdFieldName()])
            ->where('LOWER(TRIM(name)) = ?', \mb_strtolower(\trim($name)))
            ->where(SyncableInterface::DELETED_AT . ' IS NULL')
            ->limit(1);
        if ($exceptId !== null) {
            $select->where($this->getIdFieldName() . ' <> ?', $exceptId);
        }

        return $connection->fetchOne($select) !== false;
    }
}
