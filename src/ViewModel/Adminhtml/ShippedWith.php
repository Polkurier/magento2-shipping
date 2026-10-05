<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\ViewModel\Adminhtml;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNames;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\Details\SentOrder;
use Polkurier\Shipping\Model\Shipment\ScreenContext;
use Polkurier\Shipping\Model\ShippingChoiceStore;

/**
 * For the shipping information of the order and shipment pages, under the order's shipping, which
 * follows its latest Polkurier order (ActualShipping): the shipping the customer chose when that
 * differs, and the carrier and pickup point of a Polkurier order that went otherwise, e.g. an
 * earlier part of the order.
 */
readonly class ShippedWith implements ArgumentInterface
{
    public function __construct(
        private CollectionFactory $collectionFactory,
        private CarrierNames $carrierNames,
        private OrderPointStore $pointStore,
        private SentOrder $sentOrder,
        private ScreenContext $context,
        private ShippingChoiceStore $choices,
    ) {}

    /**
     * Each carrier and point the order's placed Polkurier orders went with, once.
     *
     * @return list<array{carrier: string, point: string, point_label: string, changed: bool}>
     */
    public function forOrder(Order $order): array
    {
        return $this->describe($order, [ShipmentInterface::ORDER_ID => (string)$order->getEntityId()]);
    }

    /**
     * @return list<array{carrier: string, point: string, point_label: string, changed: bool}>
     */
    public function forShipment(Order $order, int $magentoShipmentId): array
    {
        return $this->describe($order, [
            ShipmentInterface::ORDER_ID => (string)$order->getEntityId(),
            ShipmentInterface::SHIPMENT_ID => (string)$magentoShipmentId,
        ]);
    }

    /**
     * The shipping the customer chose, when the order's was changed to what its Polkurier order
     * went with (ActualShipping).
     */
    public function getCustomerChoice(Order $order): ?string
    {
        $choice = $this->choices->get((int)$order->getEntityId());

        return $choice !== null ? (string)$choice->getShippingDescription() : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array{carrier: string, point: string, point_label: string, changed: bool}>
     */
    private function describe(Order $order, array $filters): array
    {
        $methodCode = Polkurier::methodCodeOf((string)$order->getShippingMethod());
        if ($methodCode === null) {
            return [];
        }

        return $this->entries(
            $this->placed($filters),
            $methodCode,
            $this->pointStore->getForOrder((int)$order->getEntityId()),
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return ShipmentInterface[] placed ones, oldest first
     */
    private function placed(array $filters): array
    {
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::STATE, ShipmentInterface::STATE_CREATED)
            ->setOrder(ShipmentInterface::ENTITY_ID, 'ASC');
        foreach ($filters as $field => $value) {
            $collection->addFieldToFilter($field, $value);
        }

        /** @var ShipmentInterface[] $items */
        $items = $collection->getItems();

        return \array_values($items);
    }

    /**
     * Each carrier and point once.
     *
     * @param ShipmentInterface[] $shipments
     * @return list<array{carrier: string, point: string, point_label: string, changed: bool}>
     */
    private function entries(array $shipments, string $methodCode, ?OrderPointInterface $chosen): array
    {
        $chosenPoint = $chosen !== null ? $chosen->getPointId() : '';
        $shipped = [];
        foreach ($shipments as $shipment) {
            // As Polkurier reports it, or as it was sent while that's not been read yet.
            $details = $shipment->getDetails() ?: $this->sentOrder->read((string)$shipment->getRequest());
            $recipient = \is_array($details['recipient'] ?? null) ? $details['recipient'] : [];
            $point = (string)($recipient['point_id'] ?? '');
            $label = (string)($recipient['point_label'] ?? '');
            $key = $shipment->getCarrierCode() . '|' . $point;
            if (isset($shipped[$key])) {
                continue;
            }
            // Other than the order's shipping says.
            $changed = $shipment->getCarrierCode() !== $methodCode || $point !== $chosenPoint;
            // Its address isn't kept until the status update reads the order back: the order's
            // own point has it, another is looked up, for what's shown.
            if ($label === '' && $point !== '' && $point === $chosenPoint) {
                $label = (string)$chosen?->getPointLabel();
            }
            if ($changed && $label === '' && $point !== '') {
                $found = $this->context->findRecipientPoint($shipment->getCarrierCode(), $point);
                $label = $found ? (string)$found->getAddress() : '';
            }
            $shipped[$key] = [
                'carrier' => $this->carrierNames->get($shipment->getCarrierCode()),
                'point' => $point,
                'point_label' => $label,
                'changed' => $changed,
            ];
        }

        return \array_values($shipped);
    }
}
