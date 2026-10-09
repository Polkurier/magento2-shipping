<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * The polkurier_address table.
 */
class Address extends AbstractDb
{
    use NamedEntriesTrait;

    public const string TABLE_NAME = 'polkurier_address';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'address_id');
    }
}
