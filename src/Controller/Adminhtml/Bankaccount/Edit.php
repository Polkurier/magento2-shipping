<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bankaccount;

use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractEdit;
use Polkurier\Shipping\Model\Admin\BankAccountHandler;

/**
 * The form of a new or existing bank account.
 */
class Edit extends AbstractEdit
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';

    public function __construct(Context $context, BankAccountHandler $handler)
    {
        parent::__construct($context, $handler);
    }
}
