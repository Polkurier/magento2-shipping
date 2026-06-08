<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Pack\Template;

use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;

class Index extends BaseAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::pack_templates';

    public function execute()
    {
        if ($this->getRequest()->getQuery('ajax')) {
            $resultForward = $this->resultForwardFactory->create();
            $resultForward->forward('grid');

            return $resultForward;
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Polkurier_Shipping::pack_templates');
        $resultPage->getConfig()->getTitle()->prepend(\__('Manage pack templates'));
        $resultPage->addBreadcrumb('Polkurier', 'Polkurier');
        $resultPage->addBreadcrumb(\__('Settings'), \__('Settings'));
        $resultPage->addBreadcrumb(\__('Manage pack templates'), \__('Manage addresses'));

        return $resultPage;
    }
}
