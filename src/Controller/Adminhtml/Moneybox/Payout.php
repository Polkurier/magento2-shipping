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
 * The payout request form.
 */
class Payout extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::money_box_payout';

    public function execute(): Page
    {
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        if (!$page instanceof Page) {
            throw new \LogicException('The admin area renders backend pages.');
        }
        $page->setActiveMenu('Polkurier_Shipping::money_box');
        $page->getConfig()->getTitle()->prepend((string)\__('Request a payout'));

        return $page;
    }
}
