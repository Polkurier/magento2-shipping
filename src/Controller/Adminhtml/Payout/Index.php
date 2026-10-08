<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Payout;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Payouts of cash collected on delivery.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::payouts';

    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Polkurier_Shipping::payouts');
        $page->getConfig()->getTitle()->prepend((string)__('Cash on delivery payouts'));

        return $page;
    }
}
