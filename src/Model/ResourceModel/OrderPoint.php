<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class OrderPoint extends AbstractDb
{
    public const string TABLE_NAME = 'polkurier_order_point';

    /**
     * The key is the order's id, not generated here.
     *
     * @var bool
     */
    protected $_isPkAutoIncrement = false;

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'order_id');
    }
}
