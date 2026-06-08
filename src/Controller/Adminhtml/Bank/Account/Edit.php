<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bank\Account;

use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;

class Edit extends BaseAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Polkurier_Shipping::bank_accounts');
        $resultPage->getConfig()->getTitle()->prepend(\__('New bank account number'));

        return $resultPage;
    }
}
