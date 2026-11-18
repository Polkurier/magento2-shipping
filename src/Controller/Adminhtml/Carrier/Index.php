<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Carrier;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * The carriers grid.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Magento_Shipping::carriers';
    public const string ROUTE = 'polkurier/carrier/index';

    /**
     * Opened from the admin's notifications, whose links have no secret key.
     *
     * @var string[]
     */
    protected $_publicActions = ['index'];

    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Polkurier_Shipping::carriers');
        $page->getConfig()->getTitle()->prepend((string)__('Carriers'));

        return $page;
    }
}
