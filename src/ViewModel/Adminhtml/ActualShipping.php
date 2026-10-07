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
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Shipment\Details\SentOrderReader;
use Polkurier\Shipping\Model\Shipment\Screen\ScreenDataProvider;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\ShippingChoice\ShippingChoiceRepository;

/**
 * For the shipping information of the order and shipment pages, under the order's shipping, which
 * follows its latest Polkurier order (ActualShippingUpdater): the shipping the customer chose when that
 * differs, and the carrier and pickup point of a Polkurier order that went otherwise, e.g. an
 * earlier part of the order.
 */
readonly class ActualShipping implements ArgumentInterface
{
    public function __construct(
        private ShipmentFinder $finder,
        private CarrierNameProvider $carrierNameProvider,
        private OrderPointRepositoryInterface $orderPointRepository,
        private SentOrderReader $sentOrderReader,
        private ScreenDataProvider $screenData,
        private ShippingChoiceRepository $shippingChoiceRepository,
    ) {}

    /**
     * Each carrier and point the order's placed Polkurier orders went with, once.
     *
     * @return list<array{carrier: string, point: string, point_label: string, changed: bool}>
     */
    public function forOrder(Order $order): array
    {
        return $this->describe(
            $order,
            fn(): array => $this->finder->forOrder((int)$order->getEntityId(), [ShipmentInterface::STATE_CREATED]),
        );
    }

    /**
     * @return list<array{carrier: string, point: string, point_label: string, changed: bool}>
     */
    public function forShipment(Order $order, int $magentoShipmentId): array
    {
        return $this->describe($order, fn(): array => \array_values(\array_filter(
            $this->finder->linkedTo([$magentoShipmentId], [ShipmentInterface::STATE_CREATED]),
            static fn(ShipmentInterface $shipment): bool => $shipment->getOrderId() === (int)$order->getEntityId(),
        )));
    }

    /**
     * The shipping the customer chose, when the order's was changed to what its Polkurier order
     * went with (ActualShippingUpdater).
     */
    public function getCustomerChoice(Order $order): ?string
    {
        $choice = $this->shippingChoiceRepository->getForOrder((int)$order->getEntityId());

        return $choice !== null ? (string)$choice->getShippingDescription() : null;
    }

    /**
     * @param callable(): ShipmentInterface[] $placed the placed ones to describe, oldest first
     *
     * @return list<array{carrier: string, point: string, point_label: string, changed: bool}>
     */
    private function describe(Order $order, callable $placed): array
    {
        $methodCode = Polkurier::methodCodeOf((string)$order->getShippingMethod());
        if ($methodCode === null) {
            return [];
        }

        return $this->entries(
            $placed(),
            $methodCode,
            $this->orderPointRepository->getForOrder((int)$order->getEntityId()),
        );
    }

    /**
     * Each carrier and point once.
     *
     * @param ShipmentInterface[] $shipments
     *
     * @return list<array{carrier: string, point: string, point_label: string, changed: bool}>
     */
    private function entries(array $shipments, string $methodCode, ?OrderPointInterface $chosen): array
    {
        $chosenPoint = $chosen !== null ? $chosen->getPointId() : '';
        $shipped = [];
        foreach ($shipments as $shipment) {
            // As Polkurier reports it, or as it was sent while that's not been read yet.
            $details = $shipment->getDetails() ?: $this->sentOrderReader->read((string)$shipment->getRequest());
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
                $found = $this->screenData->findRecipientPoint($shipment->getCarrierCode(), $point);
                $label = $found ? (string)$found->getAddress() : '';
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
