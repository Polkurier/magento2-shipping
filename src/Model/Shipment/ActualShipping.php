<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\App\Area;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Attribute as SalesAttribute;
use Magento\Sales\Model\ResourceModel\GridInterface;
use Magento\Store\Model\App\Emulation;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Checkout\PointDescription;
use Polkurier\Shipping\Model\OrderComments;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\Details\SentOrder;
use Polkurier\Shipping\Model\ShippingChoiceStore;

/**
 * Keeps the order's shipping what was actually sent: its shipping method, its description and its
 * pickup point follow the carrier and point of its latest placed Polkurier order, so the order
 * page, the order grid, e-mails and the API all show them. The customer's choice is kept on the
 * side (ShippingChoiceStore) and comes back once no placed Polkurier order is left. The shipping
 * amount the customer paid stays as it was.
 *
 * The order is the repository's instance, which a page saving the order later holds too, and only
 * its shipping fields are written, so nothing else of it is saved again.
 */
class ActualShipping
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly ShippingChoiceStore $choices,
        private readonly OrderPointStore $pointStore,
        private readonly SentOrder $sentOrder,
        private readonly ScreenContext $context,
        private readonly MagentoShipment $magentoShipment,
        private readonly PointDescription $pointDescription,
        private readonly Emulation $emulation,
        private readonly SalesAttribute $salesAttribute,
        private readonly GridInterface $orderGrid,
        private readonly OrderComments $comments,
    ) {}

    public function sync(int $orderId): void
    {
        $order = $this->orderRepository->get($orderId);
        if (!$order instanceof Order || Polkurier::methodCodeOf((string)$order->getShippingMethod()) === null) {
            return;
        }
        $choice = $this->choices->get($orderId);
        $latest = $this->latestPlaced($orderId);
        if ($latest === null) {
            if ($choice !== null) {
                $this->restore($order);
            }

            return;
        }
        [$carrier, $point] = $this->sentTo($latest);
        $current = $this->pointStore->getForOrder($orderId);
        if ($carrier === Polkurier::methodCodeOf((string)$order->getShippingMethod()) && $point === (string)$current?->getPointId()) {
            return;
        }
        if ($choice !== null && $carrier === Polkurier::methodCodeOf((string)$choice->getShippingMethod())
            && $point === (string)$choice->getPointId()
        ) {
            $this->restore($order);

            return;
        }

        $this->choices->keep($orderId, $order->getShippingMethod(), $order->getShippingDescription(), $current);
        $label = null;
        $cod = false;
        if ($point !== '' && $point === (string)$current?->getPointId()) {
            $label = $current->getPointLabel();
            $cod = $current->isPointCod();
        } elseif ($point !== '') {
            $found = $this->context->findRecipientPoint($carrier, $point);
            $label = $found ? $found->getAddress() : null;
            $cod = $found && $found->isCod();
        }
        $description = $this->describe($order, $carrier, $point, $label);
        $this->write($order, Polkurier::CODE . '_' . $carrier, $description);
        if ($point !== '') {
            $this->pointStore->replaceForOrder($orderId, $carrier, $point, $label, $cod);
        } else {
            $this->pointStore->deleteForOrder($orderId);
        }
        $this->comments->add($order, \__(
            'Shipping changed to what Polkurier order %1 went with: %2. The customer chose: %3.',
            (string)$latest->getPolkurierNumber(),
            $description,
            (string)$this->choices->get($orderId)?->getShippingDescription(),
        ));
    }

    /**
     * Puts the customer's choice back.
     */
    private function restore(Order $order): void
    {
        $orderId = (int)$order->getEntityId();
        $choice = $this->choices->get($orderId);
        if ($choice === null) {
            return;
        }
        $this->write($order, (string)$choice->getShippingMethod(), (string)$choice->getShippingDescription());
        if ((string)$choice->getPointId() !== '') {
            $this->pointStore->replaceForOrder(
                $orderId,
                (string)$choice->getPointMethodCode(),
                (string)$choice->getPointId(),
                $choice->getPointLabel(),
                $choice->isPointCod(),
            );
        } else {
            $this->pointStore->deleteForOrder($orderId);
        }
        $this->choices->delete($orderId);
        $this->comments->add($order, \__('Shipping back to the customer\'s choice: %1.', (string)$choice->getShippingDescription()));
    }

    private function write(Order $order, string $method, string $description): void
    {
        $order->setShippingMethod($method)->setShippingDescription($description);
        $this->salesAttribute->saveAttribute($order, ['shipping_method', 'shipping_description']);
        $this->orderGrid->refresh((int)$order->getEntityId());
    }

    /**
     * As the order's own description reads, in its store's language: "Paczkomat InPost, pickup
     * point WAW01A (address)".
     */
    private function describe(Order $order, string $carrier, string $point, ?string $label): string
    {
        $storeId = (int)$order->getStoreId();
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        try {
            $title = $this->magentoShipment->getTrackTitle($order, $carrier);

            return $point !== '' ? $this->pointDescription->describe($title, $point, $label) : $title;
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    private function latestPlaced(int $orderId): ?ShipmentInterface
    {
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::ORDER_ID, (string)$orderId)
            ->addFieldToFilter(ShipmentInterface::STATE, ShipmentInterface::STATE_CREATED)
            ->setOrder(ShipmentInterface::ENTITY_ID, 'DESC')
            ->setPageSize(1);
        foreach ($collection->getItems() as $shipment) {
            /** @var ShipmentInterface $shipment */
            return $shipment;
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string} the carrier code and the pickup point id ('' without one)
     */
    private function sentTo(ShipmentInterface $shipment): array
    {
        // As Polkurier reports it, or as it was sent while that's not been read yet.
        $details = $shipment->getDetails() ?: $this->sentOrder->read((string)$shipment->getRequest());
        $recipient = \is_array($details['recipient'] ?? null) ? $details['recipient'] : [];

        return [$shipment->getCarrierCode(), (string)($recipient['point_id'] ?? '')];
    }
}
