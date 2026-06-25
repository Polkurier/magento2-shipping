<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Pack\Template;

use Magento\Framework\View\Result\Page;
use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;

class Edit extends BaseAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::pack_templates';

    /**
     * @return Page
     */
    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Polkurier_Shipping::pack_templates');
        $resultPage->getConfig()->getTitle()->prepend(
            $this->getRequest()->getParam('id')
                ? \__('Edit pack template')
                : \__('New pack template')
        );

        return $resultPage;
    }
}
