<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Order;

use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;

class Index extends BaseAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::orders';

    public function execute()
    {
        if ($this->getRequest()->getQuery('ajax')) {
            $resultForward = $this->resultForwardFactory->create();
            $resultForward->forward('grid');

            return $resultForward;
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Polkurier_Shipping::orders');
        $resultPage->getConfig()->getTitle()->prepend(\__('Orders'));
        $resultPage->addBreadcrumb('Polkurier', 'Polkurier');
        $resultPage->addBreadcrumb(\__('Sales'), \__('Sales'));
        $resultPage->addBreadcrumb(\__('Orders'), \__('Orders'));

        return $resultPage;
    }
}
