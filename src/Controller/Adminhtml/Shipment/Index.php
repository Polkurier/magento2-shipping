<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipments';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Backend\Model\View\Result\Page
    {
        $page = $this->pageFactory->create();
        if (!$page instanceof \Magento\Backend\Model\View\Result\Page) {
            throw new \LogicException('The admin area renders backend pages.');
        }
        $page->setActiveMenu('Polkurier_Shipping::shipments');
        $page->getConfig()->getTitle()->prepend((string)\__('Polkurier shipments'));

        return $page;
    }
}
