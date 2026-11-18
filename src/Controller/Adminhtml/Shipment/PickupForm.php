<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * The date and window of a collective pickup for the chosen shipments.
 */
class PickupForm extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Magento_Sales::ship';
    public const string ROUTE = 'polkurier/shipment/pickupForm';

    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Polkurier_Shipping::shipments');
        $page->getConfig()->getTitle()->prepend((string)__('Book a collective pickup'));

        return $page;
    }
}
