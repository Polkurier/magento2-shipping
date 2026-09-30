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
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;
use Polkurier\Shipping\Model\Shipment\OwnOrders;

/**
 * The Polkurier shipment form for one order.
 */
class Create extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly OwnOrders $ownOrders,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        try {
            $order = $this->ownOrders->get($orderId);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->resultRedirectFactory->create()->setPath('sales/order/view', ['order_id' => $orderId]);
        }

        $page = $this->pageFactory->create();
        if (!$page instanceof \Magento\Backend\Model\View\Result\Page) {
            throw new \LogicException('The admin area renders backend pages.');
        }
        $page->setActiveMenu('Magento_Sales::sales_order');
        $page->getConfig()->getTitle()->prepend((string)\__('Ship order #%1 with Polkurier', $order->getIncrementId()));

        return $page;
    }
}
