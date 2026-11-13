<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Native;

use Magento\Framework\App\Area;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Attribute as SalesAttribute;
use Magento\Sales\Model\ResourceModel\GridInterface;
use Magento\Store\Model\App\Emulation;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\PickupPoint\PointDescriptionBuilder;
use Polkurier\Shipping\Model\Shipment\Details\SentOrderReader;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Screen\ScreenDataProvider;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\ShippingChoice\ShippingChoiceRepository;

/**
 * Keeps the order's shipping matching what was actually sent: its shipping method, its description
 * and its pickup point follow the carrier and point of its latest placed Polkurier order, so the
 * order page, the order grid, e-mails and the API all show them. The customer's choice is kept on
 * the side ({@see ShippingChoiceRepository}) and comes back once no placed Polkurier order is left.
 * The shipping amount the customer paid stays as it was. An order placed with another carrier's
 * method follows a Polkurier order linked to it as well
 * ({@see \Polkurier\Shipping\Model\Shipment\Status\TrackAdoptionService}).
 *
 * The order is the repository's instance, which a page saving the order later holds too, and only
 * its shipping fields are written, so nothing else of it is saved again.
 */
readonly class ActualShippingUpdater
{
    public function __construct(
        private OrderLoader $orderLoader,
        private ShipmentFinder $shipmentFinder,
        private ShippingChoiceRepository $shippingChoiceRepository,
        private OrderPointRepositoryInterface $orderPointRepository,
        private SentOrderReader $sentOrderReader,
        private ScreenDataProvider $screenDataProvider,
        private NativeShipmentService $nativeShipmentService,
        private PointDescriptionBuilder $pointDescriptionBuilder,
        private Emulation $emulation,
        private SalesAttribute $salesAttribute,
        private GridInterface $orderGrid,
        private NativeFollowUpService $nativeFollowUpService,
    ) {}

    /**
     * @param bool $note whether the change is noted on the order: not when a Polkurier order has just
     *     been placed or found, whose own note says what the customer chose
     *     ({@see NativeFollowUpService::notePlaced()})
     */
    public function sync(int $orderId, bool $note = true): void
    {
        $order = $this->orderLoader->find($orderId);
        if ($order === null) {
            return;
        }
        $choice = $this->shippingChoiceRepository->getForOrder($orderId);
        $latest = $this->findLatestPlaced($orderId);
        if ($latest === null) {
            if ($choice !== null) {
                $this->restore($order, $note);
            }

            return;
        }
        [$carrier, $point] = $this->getCarrierAndPoint($latest);
        $current = $this->orderPointRepository->getForOrder($orderId);
        if ($carrier === Polkurier::extractMethodCode((string)$order->getShippingMethod())
            && $point === (string)$current?->getPointId()
        ) {
            return;
        }
        if ($choice !== null && $carrier === Polkurier::extractMethodCode((string)$choice->getShippingMethod())
            && $point === (string)$choice->getPointId()
        ) {
            $this->restore($order, $note);

            return;
        }

        $this->shippingChoiceRepository->keep(
            $orderId,
            $order->getShippingMethod(),
            $order->getShippingDescription(),
            $current,
        );
        $label = null;
        $cod = null;
        if ($point !== '' && $point === (string)$current?->getPointId()) {
            $label = $current->getPointLabel();
            $cod = $current->getPointCod();
        } elseif ($point !== '') {
            $found = $this->screenDataProvider->findRecipientPoint($carrier, $point);
            $label = $found ? $found->address : null;
            $cod = $found ? $found->cod : null;
        }
        $description = $this->describe($order, $carrier, $point, $label);
        $this->write($order, Polkurier::buildShippingMethod($carrier), $description);
        if ($point !== '') {
            $this->orderPointRepository->replaceForOrder($orderId, $carrier, $point, $label, $cod);
        } else {
            $this->orderPointRepository->deleteForOrder($orderId);
        }
        if ($note) {
            $this->nativeFollowUpService->addComment($order, __(
                'Shipping method changed to: %1 (Polkurier order %2). Customer\'s choice: %3.',
                $description,
                (string)$latest->getPolkurierNumber(),
                (string)$this->shippingChoiceRepository->getForOrder($orderId)?->getShippingDescription(),
            ));
        }
    }

    /**
     * Puts the customer's choice back.
     */
    private function restore(Order $order, bool $note): void
    {
        $orderId = (int)$order->getEntityId();
        $choice = $this->shippingChoiceRepository->getForOrder($orderId);
        if ($choice === null) {
            return;
        }
        $this->write($order, (string)$choice->getShippingMethod(), (string)$choice->getShippingDescription());
        if ((string)$choice->getPointId() !== '') {
            $this->orderPointRepository->replaceForOrder(
                $orderId,
                (string)$choice->getPointMethodCode(),
                (string)$choice->getPointId(),
                $choice->getPointLabel(),
                $choice->getPointCod(),
            );
        } else {
            $this->orderPointRepository->deleteForOrder($orderId);
        }
        $this->shippingChoiceRepository->deleteForOrder($orderId);
        if ($note) {
            $this->nativeFollowUpService->addComment($order, __(
                'Shipping method changed back to the customer\'s choice: %1.',
                (string)$choice->getShippingDescription(),
            ));
        }
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
            $title = $this->nativeShipmentService->getTrackTitle($order, $carrier);

            return $point !== '' ? $this->pointDescriptionBuilder->describe($title, $point, $label) : $title;
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    private function findLatestPlaced(int $orderId): ?ShipmentInterface
    {
        return $this->shipmentFinder->findLatestForOrder($orderId, [ShipmentInterface::STATE_CREATED]);
    }

    /**
     * @return array{0: string, 1: string} the carrier code and the pickup point id ('' without one)
     */
    private function getCarrierAndPoint(ShipmentInterface $shipment): array
    {
        // As Polkurier reports it, or as it was sent while that's not been read yet.
        $details = $shipment->getDetails() ?: $this->sentOrderReader->read((string)$shipment->getRequest());
        $recipient = \is_array($details['recipient'] ?? null) ? $details['recipient'] : [];

        return [$shipment->getCarrierCode(), (string)($recipient['point_id'] ?? '')];
    }
}
