<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Address;

use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;

class Edit extends BaseAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::addresses';

    public function execute()
    {
//        $this->_getSession()->setCustomerData($customerData);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Polkurier_Shipping::addresses');

//        if ($isExistingCustomer) {
//            $resultPage->getConfig()->getTitle()->prepend($this->_viewHelper->getCustomerName($customer));
//        } else {
            $resultPage->getConfig()->getTitle()->prepend(\__('New address'));
//        }

        return $resultPage;
    }
}
