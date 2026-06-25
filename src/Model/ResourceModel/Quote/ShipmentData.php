<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Quote;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ShipmentData extends AbstractDb
{
    public const string TABLE_NAME = 'polkurier_quote';
    public const string ID_FIELD = 'quote_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(static::TABLE_NAME, static::ID_FIELD);
    }
}
