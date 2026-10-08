<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bankaccount;

use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Controller\Adminhtml\AbstractIndex;
use Polkurier\Shipping\Model\BankAccount\BankAccountHandler;

/**
 * The bank accounts grid.
 */
class Index extends AbstractIndex
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';

    public function __construct(
        Context $context,
        BankAccountHandler $bankAccountHandler,
    ) {
        parent::__construct($context, $bankAccountHandler);
    }
}
