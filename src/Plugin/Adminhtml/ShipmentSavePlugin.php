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
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;

/**
 * On Magento's shipment page, a shipment with a label reports itself in Magento's own message.
 * When Polkurier had no label ready yet, that message is replaced by one that says so.
 *
 * A shipment saved without a label is Magento's alone, for an order of any carrier: one of a
 * Polkurier order gets its Polkurier order later, from its own page. It isn't saved while the order
 * has a Polkurier order that is still to get a new Magento shipment of its own: one not confirmed,
 * or one placed whose Magento shipment isn't created yet. That shipment is created automatically
 * from the items left, and would find them taken.
 */
readonly class ShipmentSavePlugin
{
    public function __construct(
        private OrderLoader $orderLoader,
        private ShipmentFinder $shipmentFinder,
        private ManagerInterface $messageManager,
        private RedirectFactory $redirectFactory,
        private NativeLabelService $nativeLabelService,
    ) {}

    public function aroundExecute(Save $subject, callable $proceed): mixed
    {
        $request = $subject->getRequest();
        $data = (array)$request->getParam('shipment', []);
        $orderId = (int)$request->getParam('order_id');
        if (empty($data['create_shipping_label'])) {
            $order = $this->orderLoader->findForPolkurier($orderId);
            $pending = $order !== null ? $this->findPending($orderId) : null;
            if ($order !== null && $pending !== null) {
                $this->tellPending($order, $pending);

                return $this->redirectFactory->create()
                    ->setPath('adminhtml/order_shipment/new', ['order_id' => $orderId]);
            }
        }
        $result = $proceed();
        $this->tellUnreadyLabel();

        return $result;
    }

    /**
     * The order's Polkurier order still to get a new Magento shipment, if any. One for an existing
     * Magento shipment goes on that one, and takes no items.
     */
    private function findPending(int $orderId): ?ShipmentInterface
    {
        $shipments = $this->shipmentFinder->forOrder($orderId, [
            ShipmentInterface::STATE_PENDING,
            ShipmentInterface::STATE_UNKNOWN,
            ShipmentInterface::STATE_CREATED,
        ]);
        foreach ($shipments as $shipment) {
            if ($shipment->getShipmentId() === null && $shipment->getTargetShipmentId() === null) {
                return $shipment;
            }
        }

        return null;
    }

    private function tellPending(Order $order, ShipmentInterface $pending): void
    {
        if ($pending->getState() === ShipmentInterface::STATE_CREATED) {
            $this->messageManager->addErrorMessage(__(
                'Order #%1 has Polkurier order %2, whose Magento shipment isn\'t created yet. '
                . 'It is created automatically; the rest of the order can be shipped once it is. Nothing was shipped.',
                $order->getIncrementId(),
                $pending->getPolkurierNumber(),
            ));

            return;
        }
        $this->messageManager->addErrorMessage(__(
            'Order #%1 has a Polkurier order that Polkurier hasn\'t confirmed, and it may or may not have been placed. '
            . 'It is checked automatically; the rest of the order can be shipped once it is settled. '
            . 'Nothing was shipped.',
            $order->getIncrementId(),
        ));
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
