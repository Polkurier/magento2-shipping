<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bankaccount;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Polkurier\Shipping\Controller\Adminhtml\AbstractSave;
use Polkurier\Shipping\Model\BankAccount\BankAccountHandler;

/**
 * Saves a bank account from its form, sent to Polkurier with the code or PIN given there.
 */
class Save extends AbstractSave
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';

    public function __construct(
        Context $context,
        BankAccountHandler $bankAccountHandler,
        DataPersistorInterface $dataPersistor,
    ) {
        parent::__construct($context, $bankAccountHandler, $dataPersistor);
    }

    protected function getTransientFields(): array
    {
        return BankAccountHandler::CONFIRMATION_FIELDS;
    }
}
