<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

use Polkurier\Shipping\Enum\SyncEntityEnum;

/**
 * What one synchronization of one kind of entry did, as counts.
 *
 * @api
 */
interface SyncResultInterface
{
    public function getEntityType(): SyncEntityEnum;

    /**
     * Entries new in Polkurier, added in Magento.
     */
    public function getCreated(): int;

    /**
     * Entries changed in Polkurier, updated in Magento.
     */
    public function getPulled(): int;

    /**
     * Entries changed in Magento, sent to Polkurier.
     */
    public function getPushed(): int;

    /**
     * Entries removed on either side, as the other side deleted them.
     */
    public function getDeleted(): int;

    public function getUnchanged(): int;

    /**
     * Entries saved in Magento that Polkurier takes only once the merchant confirms them.
     */
    public function getUnconfirmed(): int;

    public function getFailed(): int;

    /**
     * Why those failed.
     *
     * @return string[]
     */
    public function getErrors(): array;
}
