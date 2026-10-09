<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\BankAccount;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\BankAccount as Model;
use Polkurier\Shipping\Model\ResourceModel\BankAccount as ResourceModel;

/**
 * The bank accounts.
 *
 * @method Model[] getItems()
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'account_id';

    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
