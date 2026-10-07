<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Placement\FailedShipmentManager;
use Polkurier\Shipping\Model\Shipment\Screen\RetriableShipmentFinder;

/**
 * The Polkurier shipment form for one order.
 */
class Create extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        private readonly OrderLoader $orderLoader,
        private readonly RetriableShipmentFinder $retriableShipmentFinder,
        private readonly FailedShipmentManager $failedShipmentManager,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        try {
            $order = $this->orderLoader->getForPolkurier($orderId);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->resultRedirectFactory->create()->setPath('sales/order/view', ['order_id' => $orderId]);
        }

        $retried = $this->retriableShipmentFinder->find(
            $order,
            $this->getRequest()->getParam(RetriableShipmentFinder::PARAM),
        );
        if ($retried !== null) {
            $this->messageManager->addNoticeMessage(__(
                'Starting from the shipment that wasn\'t placed on %1, as it was filled in then. '
                . 'Why it wasn\'t placed: %2',
                $this->failedShipmentManager->describe($retried)['tried'],
                (string)$retried->getLastError(),
            ));
        } elseif (($failed = $this->retriableShipmentFinder->findForOrder($order)) !== null) {
            $described = $this->failedShipmentManager->describe($failed);
            // Placing another replaces it: said before, so it isn't lost unnoticed.
            $this->messageManager->addNoticeMessage(__(
                'A shipment of this order wasn\'t placed on %1: %2. Placing now places it, as filled in here; '
                . '"Send again" in the order\'s Polkurier tab starts from what was filled in then. '
                . 'Unless placed or discarded, it\'s removed on %3.',
                $described['tried'],
                $described['reason'],
                $described['removed'],
            ));
        }

        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        if (!$page instanceof Page) {
            throw new \LogicException('The admin area renders backend pages.');
        }
        $page->setActiveMenu('Magento_Sales::sales_order');
        $page->getConfig()->getTitle()->prepend((string)__('Ship order #%1 with Polkurier', $order->getIncrementId()));

        return $page;
    }
}
