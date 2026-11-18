<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bankaccount;

use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Controller\Adminhtml\AbstractDelete;
use Polkurier\Shipping\Model\BankAccount\BankAccountHandler;

/**
 * Deletes a bank account in Polkurier, then here, with the PIN given on its form where needed.
 */
class Delete extends AbstractDelete
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';
    public const string ROUTE = 'polkurier/bankaccount/delete';

    public function __construct(
        Context $context,
        BankAccountHandler $bankAccountHandler,
    ) {
        parent::__construct($context, $bankAccountHandler);
    }
}
