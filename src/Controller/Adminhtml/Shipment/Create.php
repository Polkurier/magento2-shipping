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
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Result\PageFactory;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment\OwnOrders;
use Polkurier\Shipping\Model\Shipment\NotPlaced;
use Polkurier\Shipping\Model\Shipment\SendAgain;

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
        private readonly SendAgain $sendAgain,
        private readonly TimezoneInterface $timezone,
        private readonly NotPlaced $notPlaced,
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

        $retried = $this->sendAgain->find($order, $this->getRequest()->getParam(SendAgain::PARAM));
        if ($retried !== null) {
            $this->messageManager->addNoticeMessage(\__(
                'Starting from the shipment that wasn\'t placed on %1, as it was filled in then. Why it wasn\'t placed: %2',
                $this->formatDate($retried),
                (string)$retried->getLastError(),
            ));
        } elseif (($failed = $this->sendAgain->findForOrder($order)) !== null) {
            // Placing another replaces it: said before, so it isn't lost unnoticed.
            $this->messageManager->addNoticeMessage(\__(
                'A shipment of this order wasn\'t placed on %1: %2. Placing now places it, as filled in here; "Send again" in the order\'s Polkurier tab starts from what was filled in then. Unless placed or discarded, it\'s removed on %3.',
                $this->formatDate($failed),
                \rtrim((string)$failed->getLastError(), '. '),
                $this->formatUtc($this->notPlaced->expiresAt($failed)),
            ));
        }

        $page = $this->pageFactory->create();
        if (!$page instanceof \Magento\Backend\Model\View\Result\Page) {
            throw new \LogicException('The admin area renders backend pages.');
        }
        $page->setActiveMenu('Magento_Sales::sales_order');
        $page->getConfig()->getTitle()->prepend((string)\__('Ship order #%1 with Polkurier', $order->getIncrementId()));

        return $page;
    }

    private function formatDate(ShipmentInterface $shipment): string
    {
        return $this->formatUtc((string)$shipment->getCreatedAt());
    }

    private function formatUtc(string $utc): string
    {
        return $this->timezone->formatDateTime(
            new \DateTimeImmutable($utc, new \DateTimeZone('UTC')),
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::SHORT,
        );
    }
}
