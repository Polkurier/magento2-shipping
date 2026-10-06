<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bankaccount;

use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractSync;
use Polkurier\Shipping\Model\Admin\BankAccountHandler;
use Polkurier\Shipping\Model\Config;

/**
 * Synchronizes the bank accounts with the Polkurier account now.
 */
class Sync extends AbstractSync
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';

    public function __construct(Context $context, BankAccountHandler $handler, Config $config)
    {
        parent::__construct($context, $handler, $config);
    }
}
