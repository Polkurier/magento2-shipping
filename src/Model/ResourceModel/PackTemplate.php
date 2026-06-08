<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PackTemplate extends AbstractDb
{
    public const string TABLE_NAME = 'polkurier_pack_template';
    public const string ID_FIELD = 'template_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(static::TABLE_NAME, static::ID_FIELD);
    }
}
