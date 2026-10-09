<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bankaccount;

use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Controller\Adminhtml\AbstractEdit;
use Polkurier\Shipping\Model\BankAccount\BankAccountHandler;

/**
 * The form of a new or existing bank account.
 */
class Edit extends AbstractEdit
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';

    public function __construct(
        Context $context,
        BankAccountHandler $bankAccountHandler,
    ) {
        parent::__construct($context, $bankAccountHandler);
    }
}
