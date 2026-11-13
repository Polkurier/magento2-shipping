<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\ViewModel\Adminhtml;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Shipment\Details\SentOrderReader;
use Polkurier\Shipping\Model\Shipment\Screen\ScreenDataProvider;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\ShippingChoice\ShippingChoiceRepository;

/**
 * For the shipping information of the order and shipment pages. Under the order's shipping, which
 * follows its latest Polkurier order ({@see \Polkurier\Shipping\Model\Shipment\Native\ActualShippingUpdater}),
 * it shows the shipping the customer chose when that differs, and the carrier and pickup point of
 * any Polkurier order that went otherwise, e.g. an earlier part of the order.
 */
readonly class ActualShipping implements ArgumentInterface
{
    public function __construct(
        private ShipmentFinder $shipmentFinder,
        private CarrierNameProvider $carrierNameProvider,
        private OrderPointRepositoryInterface $orderPointRepository,
        private SentOrderReader $sentOrderReader,
        private ScreenDataProvider $screenDataProvider,
        private ShippingChoiceRepository $shippingChoiceRepository,
    ) {}

    /**
     * Each carrier and point the order's placed Polkurier orders went with, once.
     *
     * @return list<array{carrier: string, point: string, point_label: string, changed: bool}>
     */
    public function getForOrder(Order $order): array
    {
        if (!Polkurier::isOrderOf($order)) {
            return [];
        }

        return $this->describe(
            $order,
            $this->shipmentFinder->findForOrder((int)$order->getEntityId(), [ShipmentInterface::STATE_CREATED]),
        );
    }

    /**
     * @return list<array{carrier: string, point: string, point_label: string, changed: bool}>
     */
    public function getForShipment(Order $order, int $magentoShipmentId): array
    {
        if (!Polkurier::isOrderOf($order)) {
            return [];
        }
        $orderId = (int)$order->getEntityId();

        return $this->describe($order, \array_filter(
            $this->shipmentFinder->findLinkedTo([$magentoShipmentId], [ShipmentInterface::STATE_CREATED]),
            static fn(ShipmentInterface $shipment): bool => $shipment->getOrderId() === $orderId,
        ));
    }

    /**
     * The shipping the customer chose, when the order's shipping was changed to match its
     * Polkurier order ({@see \Polkurier\Shipping\Model\Shipment\Native\ActualShippingUpdater}).
     */
    public function getCustomerChoice(Order $order): ?string
    {
        $choice = $this->shippingChoiceRepository->getForOrder((int)$order->getEntityId());

        return $choice !== null ? (string)$choice->getShippingDescription() : null;
    }

    /**
     * Each carrier and point once.
     *
     * @param ShipmentInterface[] $shipments the placed ones to describe, oldest first
     *
     * @return list<array{carrier: string, point: string, point_label: string, changed: bool}>
     */
    private function describe(Order $order, array $shipments): array
    {
        $methodCode = (string)Polkurier::extractMethodCode((string)$order->getShippingMethod());
        $chosen = $this->orderPointRepository->getForOrder((int)$order->getEntityId());
        $chosenPoint = $chosen !== null ? $chosen->getPointId() : '';
        $shipped = [];
        foreach ($shipments as $shipment) {
            // As Polkurier reports it, or as it was sent until Polkurier's copy has been read.
            $details = $shipment->getDetails() ?: $this->sentOrderReader->read((string)$shipment->getRequest());
            $recipient = \is_array($details['recipient'] ?? null) ? $details['recipient'] : [];
            $point = (string)($recipient['point_id'] ?? '');
            $label = (string)($recipient['point_label'] ?? '');
            $key = $shipment->getCarrierCode() . '|' . $point;
            if (isset($shipped[$key])) {
                continue;
            }
            // Differs from what the order's shipping says.
            $changed = $shipment->getCarrierCode() !== $methodCode || $point !== $chosenPoint;
            // The point's address isn't stored until the status update reads the order back. For
            // display, the order's own point already has it; any other point is looked up.
            if ($label === '' && $point !== '' && $point === $chosenPoint) {
                $label = (string)$chosen?->getPointLabel();
            }
            if ($changed && $label === '' && $point !== '') {
                $found = $this->screenDataProvider->findRecipientPoint($shipment->getCarrierCode(), $point);
                $label = $found ? $found->address : '';
            }
            $shipped[$key] = [
                'carrier' => $this->carrierNameProvider->get($shipment->getCarrierCode()),
                'point' => $point,
                'point_label' => $label,
                'changed' => $changed,
            ];
        }

        return \array_values($shipped);
    }
}
