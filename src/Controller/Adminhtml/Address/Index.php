<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Address;

use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;

class Index extends BaseAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::addresses';

    public function execute()
    {
        if ($this->getRequest()->getQuery('ajax')) {
            $resultForward = $this->resultForwardFactory->create();
            $resultForward->forward('grid');

            return $resultForward;
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Polkurier_Shipping::addresses');
        $resultPage->getConfig()->getTitle()->prepend(\__('Manage addresses'));
        $resultPage->addBreadcrumb('Polkurier', 'Polkurier');
        $resultPage->addBreadcrumb(\__('Settings'), \__('Settings'));
        $resultPage->addBreadcrumb(\__('Manage addresses'), \__('Manage addresses'));

        return $resultPage;
    }
}
