<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Adminhtml;

use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;

/**
 * On Magento's shipment page, a shipment of an order placed with a Polkurier method is saved only
 * together with its Polkurier order, that is, only with a shipping label, which places the order.
 * When Polkurier refuses, the shipment isn't saved either. Otherwise a refused shipment could still
 * be submitted without the label, leaving the order shipped with nothing sent.
 *
 * Does nothing when the module ships nothing itself: when both the labels on Magento's page and
 * the shipment screen are turned off.
 *
 * With a label, Magento's own message reports the shipment. When Polkurier had no label ready yet,
 * that message is replaced by one that says so.
 */
readonly class ShipmentSavePlugin
{
    public function __construct(
        private OrderLoader $orderLoader,
        private ConfigProvider $configProvider,
        private ManagerInterface $messageManager,
        private RedirectFactory $redirectFactory,
        private NativeLabelService $nativeLabelService,
    ) {}

    public function aroundExecute(Save $subject, callable $proceed): mixed
    {
        $request = $subject->getRequest();
        $data = (array)$request->getParam('shipment', []);
        $orderId = (int)$request->getParam('order_id');
        if (!empty($data['create_shipping_label'])) {
            $result = $proceed();
            $this->tellUnreadyLabel();

            return $result;
        }
        if ($orderId <= 0) {
            return $proceed();
        }
        $order = $this->orderLoader->findForPolkurier($orderId);
        if ($order === null) {
            return $proceed();
        }
        $storeId = (int)$order->getStoreId();
        if ($this->configProvider->isNativeLabelsEnabled($storeId)) {
            $this->messageManager->addErrorMessage(__(
                'Order #%1 ships with a Polkurier order, so its shipment is created with a shipping label: '
                . 'that places the Polkurier order. Nothing was shipped.',
                $order->getIncrementId(),
            ));
        } elseif ($this->configProvider->isShipmentScreenEnabled($storeId)) {
            $this->messageManager->addErrorMessage(__(
                'Order #%1 ships with a Polkurier order: '
                . 'use Ship on the order page, which opens the Polkurier shipment screen. Nothing was shipped.',
                $order->getIncrementId(),
            ));
        } else {
            return $proceed();
        }

        return $this->redirectFactory->create()->setPath('adminhtml/order_shipment/new', ['order_id' => $orderId]);
    }

    /**
     * Placed, but with no label yet, while Magento's message says it created one. That message is
     * replaced, so there's only one message and it's correct.
     */
    private function tellUnreadyLabel(): void
    {
        $number = $this->nativeLabelService->takeUnreadyLabel();
        if ($number === null) {
            return;
        }
        $created = (string)__('The shipment has been created.');
        foreach ($this->messageManager->getMessages(true)->getItems() as $message) {
            if (!\str_starts_with((string)$message->getText(), $created)) {
                $this->messageManager->addMessage($message);
            }
        }
        $this->messageManager->addNoticeMessage(__(
            'The shipment has been created with Polkurier order %1, but its label isn\'t ready yet. '
            . 'Print it later from the order\'s Polkurier tab.',
            $number,
        ));
    }
}
