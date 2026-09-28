<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Address extends AbstractDb
{
    public const string TABLE_NAME = 'polkurier_address';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'address_id');
    }
}
