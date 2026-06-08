<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bank\Account;

use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;

class Index extends BaseAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';

    public function execute()
    {
        if ($this->getRequest()->getQuery('ajax')) {
            $resultForward = $this->resultForwardFactory->create();
            $resultForward->forward('grid');

            return $resultForward;
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Polkurier_Shipping::bank_accounts');
        $resultPage->getConfig()->getTitle()->prepend(\__('Manage bank account numbers'));
        $resultPage->addBreadcrumb('Polkurier', 'Polkurier');
        $resultPage->addBreadcrumb(\__('Settings'), \__('Settings'));
        $resultPage->addBreadcrumb(\__('Manage bank account numbers'), \__('Manage bank account numbers'));

        return $resultPage;
    }
}
