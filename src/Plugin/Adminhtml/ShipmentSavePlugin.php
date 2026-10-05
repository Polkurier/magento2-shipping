<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Adminhtml;

use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Shipment\NativeLabel;

/**
 * A shipment of an order placed with a Polkurier method isn't saved on Magento's shipment page
 * without its Polkurier order: only with a shipping label, which places it, and which isn't saved
 * either when Polkurier refuses. Otherwise a refused shipment could still be submitted without
 * the label, and leave the order shipped with nothing sent.
 *
 * Left alone when the module ships nothing itself: with both the labels on Magento's page and the
 * shipment screen turned off.
 *
 * With a label, Magento's own message tells about the shipment; when Polkurier had no label ready
 * yet, it's replaced by one that says so.
 */
readonly class ShipmentSavePlugin
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private Config $config,
        private ManagerInterface $messageManager,
        private RedirectFactory $redirectFactory,
        private NativeLabel $nativeLabel,
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
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException) {
            return $proceed();
        }
        if (!$order instanceof Order || !Polkurier::isOrderOf($order)) {
            return $proceed();
        }
        $storeId = (int)$order->getStoreId();
        if ($this->config->isNativeLabelsEnabled($storeId)) {
            $this->messageManager->addErrorMessage(\__(
                'Order #%1 ships with a Polkurier order, so its shipment is created with a shipping label: that places the Polkurier order. Nothing was shipped.',
                $order->getIncrementId(),
            ));
        } elseif ($this->config->isShipmentScreenEnabled($storeId)) {
            $this->messageManager->addErrorMessage(\__(
                'Order #%1 ships with a Polkurier order: use Ship on the order page, which opens the Polkurier shipment screen. Nothing was shipped.',
                $order->getIncrementId(),
            ));
        } else {
            return $proceed();
        }

        return $this->redirectFactory->create()->setPath('adminhtml/order_shipment/new', ['order_id' => $orderId]);
    }

    /**
     * Placed, but with no label yet: Magento's message says it created one. Its message is
     * replaced, so that there's one, and it's right.
     */
    private function tellUnreadyLabel(): void
    {
        $number = $this->nativeLabel->takeUnreadyLabel();
        if ($number === null) {
            return;
        }
        $created = (string)\__('The shipment has been created.');
        foreach ($this->messageManager->getMessages(true)->getItems() as $message) {
            if (!\str_starts_with((string)$message->getText(), $created)) {
                $this->messageManager->addMessage($message);
            }
        }
        $this->messageManager->addNoticeMessage(\__(
            'The shipment has been created with Polkurier order %1, but its label isn\'t ready yet. Print it later from the order\'s Polkurier tab.',
            $number,
        ));
    }
}
