<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bankaccount;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Ui\Component\MassAction\Filter;
use Polkurier\Shipping\Controller\Adminhtml\AbstractMassDelete;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\BankAccount\BankAccountHandler;
use Polkurier\Shipping\Model\ResourceModel\BankAccount\CollectionFactory;

/**
 * Deletes the bank accounts selected in their grid.
 */
class MassDelete extends AbstractMassDelete
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';

    public function __construct(
        Context $context,
        BankAccountHandler $bankAccountHandler,
        Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        parent::__construct($context, $bankAccountHandler, $filter, $flashMessageCollectorFactory);
    }

    protected function createCollection(): AbstractDb
    {
        return $this->collectionFactory->create();
    }
}
