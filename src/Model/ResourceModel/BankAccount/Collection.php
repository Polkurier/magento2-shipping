<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\BankAccount;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\BankAccount as Model;
use Polkurier\Shipping\Model\ResourceModel\BankAccount as ResourceModel;

/**
 * The bank accounts.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'account_id';

    /**
     * @return Model[]
     */
    public function getItems(): array
    {
        /** @var Model[] $items */
        $items = parent::getItems();

        return $items;
    }

    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
