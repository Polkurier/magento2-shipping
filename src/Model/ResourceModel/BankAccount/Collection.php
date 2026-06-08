<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\BankAccount;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\BankAccount;
use Polkurier\Shipping\Model\ResourceModel\BankAccount as BankAccountResource;

class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(BankAccount::class, BankAccountResource::class);
    }
}
