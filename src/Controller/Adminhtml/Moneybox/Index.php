<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Moneybox;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\Model\View\Result\Page;

/**
 * Payouts of cash collected on delivery.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::money_box';

    public function execute(): Page
    {
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        if (!$page instanceof Page) {
            throw new \LogicException('The admin area renders backend pages.');
        }
        $page->setActiveMenu('Polkurier_Shipping::money_box');
        $page->getConfig()->getTitle()->prepend((string)\__('Cash on delivery payouts'));

        return $page;
    }
}
