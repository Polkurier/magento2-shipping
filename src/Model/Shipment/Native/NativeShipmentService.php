<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Native;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\ShipmentInterface as MagentoShipmentInterface;
use Magento\Sales\Api\Data\ShipmentItemCreationInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentTrackCreationInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentTrackInterfaceFactory;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use Polkurier\Shipping\Api\Data\ShipmentDraft\ItemInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraft\PackInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;

/**
 * Ships the Magento order that a Polkurier order was placed for, and keeps its tracking numbers
 * equal to the Polkurier waybills.
 *
 * Each Magento shipment goes with one Polkurier order. A Polkurier order either ships items the
 * order has left, in a new Magento shipment, or goes on a Magento shipment that has none, such as
 * one made without a label, or one whose Polkurier order was cancelled
 * ({@see \Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService} clears it of that order's
 * label, packages and waybills).
 */
readonly class NativeShipmentService
{
    public function __construct(
        private ShipOrderInterface $shipOrder,
        private ShipmentItemCreationInterfaceFactory $itemCreationFactory,
        private OrderAmountCalculator $orderAmountCalculator,
        private ShipmentTrackCreationInterfaceFactory $trackCreationFactory,
        private ShipmentTrackInterfaceFactory $trackFactory,
        private ShipmentTrackRepositoryInterface $trackRepository,
        private ShipmentRepositoryInterface $shipmentRepository,
        private ShipmentFinder $shipmentFinder,
        private CarrierConfigProvider $carrierConfigProvider,
        private ReservedNumberRegistry $reservedNumberRegistry,
    ) {}

    /**
     * Where a new Polkurier order goes in Magento, checked before anything is placed: a new
     * Magento shipment of the items chosen, or, when the order has nothing left to ship, its latest
     * Magento shipment without a Polkurier order (the one of a cancelled order it replaces).
     *
     * @return array{qtys: array<int, float>, shipment_id: int|null, packages: array<int, array<string, mixed>>}
     *     the quantities of a new Magento shipment (empty for everything left) and its packages, or
     *     the Magento shipment to go on
     * @throws LocalizedException
     */
    public function getTarget(Order $order, ShipmentDraftInterface $draft): array
    {
        $targetId = $draft->getTargetShipmentId();
        if ($targetId !== null) {
            // A Magento shipment of a cancelled Polkurier order, chosen on its page: its items, as
            // they are, in the parcels drafted.
            $this->assertFree($order, $targetId);
            $qtys = $this->getShipmentQuantities($order, $targetId);

            return [
                'qtys' => [],
                'shipment_id' => $targetId,
                'packages' => $this->getPackages($order, $draft->getPacks(), $qtys),
            ];
        }
        if ($order->canShip()) {
            $qtys = $this->getQuantities($order, $draft->getItems());

            return [
                'qtys' => $qtys,
                'shipment_id' => null,
                'packages' => $this->getPackages($order, $draft->getPacks(), $qtys),
            ];
        }
        $free = \array_keys($this->getFreeShipments($order));
        if ($free === []) {
            throw new LocalizedException(__(
                'Everything on the order is shipped, and each of its Magento shipments has its Polkurier order. '
                . 'To send a replacement, cancel the Polkurier order it replaces first.',
            ));
        }

        return ['qtys' => [], 'shipment_id' => \max($free), 'packages' => []];
    }

    /**
     * The parcels as the packages of the new Magento shipment, in the form Magento's packaging
     * window saves them: one per parcel, with the items put in it. Checked against the quantities
     * shipped (assertPacked()).
     *
     * @param PackInterface[] $packs
     * @param array<int, float> $qtys by order item id; empty for everything left
     *
     * @return array<int, array<string, mixed>> by package number, from 1
     * @throws LocalizedException
     */
    public function getPackages(Order $order, array $packs, array $qtys): array
    {
        $this->assertPacked($order, $packs, $qtys ?: $this->getQuantitiesLeft($order));
        $shippable = $this->orderAmountCalculator->getItems($order);
        $packages = [];
        foreach ($packs as $pack) {
            $items = [];
            $value = 0.0;
            foreach ($pack->getItems() as $item) {
                $id = $item->getOrderItemId();
                if ($item->getQty() <= 0) {
                    continue;
                }
                $items[$id] = [
                    'qty' => $item->getQty(),
                    'customs_value' => $shippable[$id]['unit'],
                    'price' => $shippable[$id]['unit'],
                    'name' => $shippable[$id]['name'],
                    'weight' => $shippable[$id]['weight'],
                    'product_id' => $shippable[$id]['product_id'],
                    'order_item_id' => $id,
                ];
                $value += $shippable[$id]['unit'] * $item->getQty();
            }
            $package = [
                'params' => [
                    'container' => $pack->getPackType(),
                    'weight' => $pack->getWeight(),
                    'customs_value' => \round($value, 2),
                    'length' => $pack->getLength(),
                    'width' => $pack->getWidth(),
                    'height' => $pack->getHeight(),
                    'weight_units' => 'KILOGRAM',
                    'dimension_units' => 'CENTIMETER',
                    'content_type' => '',
                    'content_type_other' => '',
                ],
                'items' => $items,
            ];
            // Identical parcels are one row of the draft, and a package each in Magento.
            for ($i = 0; $i < \max(1, $pack->getAmount()); $i++) {
                $packages[\count($packages) + 1] = $package;
            }
        }

        return $packages;
    }

    /**
     * Checks what the parcels hold against what the shipment carries: no item it doesn't carry,
     * and no more of one than it does.
     *
     * @param PackInterface[] $packs
     * @param array<int, float> $qtys what the shipment carries, by order item id
     *
     * @throws LocalizedException
     */
    public function assertPacked(Order $order, array $packs, array $qtys): void
    {
        $shippable = $this->orderAmountCalculator->getItems($order);
        $packed = [];
        foreach ($packs as $pack) {
            foreach ($pack->getItems() as $item) {
                $id = $item->getOrderItemId();
                if ($item->getQty() < 0) {
                    throw new LocalizedException(__('A parcel cannot hold a negative quantity of an item.'));
                }
                if ($item->getQty() > 0 && !isset($qtys[$id], $shippable[$id])) {
                    throw new LocalizedException(__('A parcel holds an item the shipment doesn\'t carry.'));
                }
                $packed[$id] = ($packed[$id] ?? 0.0) + $item->getQty() * $pack->getAmount();
            }
        }
        foreach ($packed as $id => $qty) {
            if ($qty > ($qtys[$id] ?? 0.0) + 0.0001) {
                throw new LocalizedException(__(
                    'The parcels hold more of "%1" than the shipment carries.',
                    $shippable[$id]['name'],
                ));
            }
        }
    }

    /**
     * The order's Magento shipments without a Polkurier order of their own, by id, with the
     * cancelled Polkurier order a new one would replace, if any.
     *
     * @return array<int, array{increment_id: string, replaces: string|null}>
     */
    public function getFreeShipments(Order $order): array
    {
        $shipments = [];
        foreach ($order->getShipmentsCollection() as $magentoShipment) {
            $shipments[(int)$magentoShipment->getId()] = [
                'increment_id' => (string)$magentoShipment->getIncrementId(),
                'replaces' => null,
            ];
        }
        if ($shipments === []) {
            return [];
        }
        // Oldest first: what a new Polkurier order replaces is the latest cancelled one.
        foreach ($this->shipmentFinder->linkedTo(\array_keys($shipments)) as $polkurier) {
            $id = (int)$polkurier->getShipmentId();
            if (\in_array($polkurier->getState(), ShipmentInterface::LIVE_STATES, true)) {
                unset($shipments[$id]);
            } elseif (isset($shipments[$id])) {
                $shipments[$id]['replaces'] = $polkurier->getPolkurierNumber();
            }
        }
        \ksort($shipments);

        return $shipments;
    }

    /**
     * Checks that a Magento shipment of the order can take a new Polkurier order: it has none, or
     * only cancelled ones.
     *
     * @throws LocalizedException
     */
    public function assertFree(Order $order, int $magentoShipmentId): void
    {
        if (!isset($this->getFreeShipments($order)[$magentoShipmentId])) {
            throw new LocalizedException(__(
                'This Magento shipment already has a Polkurier order. To send another, cancel that one first.',
            ));
        }
    }

    /**
     * What a Magento shipment of the order carries.
     *
     * @return array<int, float> by order item id
     */
    public function getShipmentQuantities(Order $order, int $magentoShipmentId): array
    {
        $qtys = [];
        foreach ($order->getShipmentsCollection() as $magentoShipment) {
            if ((int)$magentoShipment->getId() === $magentoShipmentId) {
                foreach ($magentoShipment->getAllItems() as $item) {
                    if ((float)$item->getQty() > 0) {
                        $qtys[(int)$item->getOrderItemId()] = (float)$item->getQty();
                    }
                }
            }
        }

        return $qtys;
    }

    /**
     * What the order has left to ship.
     *
     * @return array<int, float> by order item id
     */
    public function getQuantitiesLeft(Order $order): array
    {
        $qtys = [];
        foreach ($this->orderAmountCalculator->getItems($order) as $id => $item) {
            if ($item['qty_to_ship'] > 0) {
                $qtys[$id] = (float)$item['qty_to_ship'];
            }
        }

        return $qtys;
    }

    /**
     * Checks the quantities chosen for a new Magento shipment against what the order has left.
     *
     * @param ItemInterface[] $items
     *
     * @return array<int, float> the quantities to ship by order item id; empty for everything left
     * @throws LocalizedException
     */
    public function getQuantities(Order $order, array $items): array
    {
        if ($items === []) {
            return [];
        }
        $shippable = $this->orderAmountCalculator->getItems($order);
        $qtys = [];
        foreach ($items as $item) {
            $id = $item->getOrderItemId();
            $qty = $item->getQty();
            if (!isset($shippable[$id])) {
                throw new LocalizedException(__('The order has no item %1 to ship.', $id));
            }
            if ($qty < 0 || $qty > $shippable[$id]['qty_to_ship'] + 0.0001) {
                throw new LocalizedException(__(
                    'You can ship at most %1 of "%2".',
                    (float)$shippable[$id]['qty_to_ship'],
                    $shippable[$id]['name'],
                ));
            }
            if ($qty > 0) {
                $qtys[$id] = $qty;
            }
        }
        if ($qtys === []) {
            throw new LocalizedException(__('Choose at least one item to ship.'));
        }

        return $qtys;
    }

    /**
     * Puts the Polkurier order on the Magento shipment given, or ships the quantities given (or
     * whatever is left), or, when everything is shipped, goes on the latest shipment without a
     * Polkurier order. The customer isn't e-mailed here.
     *
     * @param array<int, float> $qtys by order item id; empty for everything left
     * @param array<int, array<string, mixed>> $packages of a new Magento shipment, see getPackages()
     *
     * @return int the Magento shipment id
     * @throws LocalizedException when there is nothing to ship and no shipment to go on
     */
    public function ship(
        Order $order,
        ShipmentInterface $shipment,
        array $qtys = [],
        ?int $targetId = null,
        array $packages = [],
    ): int {
        if ($targetId === null && $order->canShip()) {
            $items = [];
            foreach ($qtys as $orderItemId => $qty) {
                $items[] = $this->itemCreationFactory->create()->setOrderItemId((int)$orderItemId)->setQty($qty);
            }
            $tracks = [];
            foreach ($shipment->getWaybills() as $waybill) {
                $tracks[] = $this->trackCreationFactory->create()
                    ->setTrackNumber($waybill)
                    ->setTitle($this->getTrackTitle($order, $shipment->getCarrierCode()))
                    ->setCarrierCode(Polkurier::CODE);
            }

            // The number the Polkurier order was sent with, taken for this shipment beforehand.
            $number = $shipment->getReservedShipmentNumber();
            if ($number !== null) {
                $this->reservedNumberRegistry->expect((int)$order->getEntityId(), $number);
            }
            try {
                // Never with the customer's e-mail: that goes last, once everything else is done
                // ({@see \Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService::ship()}).
                $shipmentId = (int)$this->shipOrder->execute(
                    (int)$order->getEntityId(),
                    $items,
                    false,
                    false,
                    null,
                    $tracks,
                );
            } finally {
                // Cleared if Magento didn't take it, so no other shipment gets it.
                $this->reservedNumberRegistry->take((int)$order->getEntityId());
            }
            $magentoShipment = $packages !== [] ? $this->shipmentRepository->get($shipmentId) : null;
            if ($magentoShipment instanceof MagentoShipmentModel) {
                // What each parcel holds, for Magento's "Show Packages". Magento keeps packages as
                // the arrays its packaging window posts, whatever its interface says, and shows
                // them that way; its own label flow stores them the same.
                $this->shipmentRepository->save(
                    $magentoShipment->setData(MagentoShipmentInterface::PACKAGES, $packages),
                );
            }

            return $shipmentId;
        }

        $free = $this->getFreeShipments($order);
        $targetId ??= $free === [] ? null : \max(\array_keys($free));
        if ($targetId === null || !isset($free[$targetId])) {
            throw new LocalizedException(__(
                'Everything on the order is shipped, and each of its Magento shipments has its Polkurier order.',
            ));
        }
        $this->release($targetId);
        $this->addTracks($order, $targetId, $shipment, $shipment->getWaybills());
        $magentoShipment = $packages !== [] ? $this->shipmentRepository->get($targetId) : null;
        if ($magentoShipment instanceof MagentoShipmentModel) {
            // The new parcels, in place of what the cancelled order's label had.
            $this->shipmentRepository->save($magentoShipment->setData(MagentoShipmentInterface::PACKAGES, $packages));
        }

        return $targetId;
    }

    /**
     * Whether the customer can still be e-mailed about the Magento shipment: no e-mail about it was
     * sent, or asked for (Magento's asynchronous sending queues it).
     */
    public static function isEmailOpen(MagentoShipmentModel $magentoShipment): bool
    {
        return !$magentoShipment->getSendEmail() && !$magentoShipment->getEmailSent();
    }

    /**
     * Readies a Magento shipment for the Polkurier order that replaces a cancelled one: the
     * cancelled order's waybills, if still there, are removed from the shipment's tracking. It
     * stays linked: a Magento shipment keeps every Polkurier order it has had.
     */
    public function release(int $magentoShipmentId): void
    {
        $waybills = [];
        foreach ($this->shipmentFinder->linkedTo([$magentoShipmentId]) as $replaced) {
            if (!\in_array($replaced->getState(), ShipmentInterface::LIVE_STATES, true)) {
                $waybills = \array_merge($waybills, $replaced->getWaybills());
            }
        }
        if ($waybills === []) {
            return;
        }
        $this->removeTracks($this->shipmentRepository->get($magentoShipmentId), $waybills);
    }

    /**
     * Adds tracks for waybills the Magento shipment does not carry yet.
     *
     * @param string[] $waybills
     */
    public function addTracks(Order $order, int $shipmentId, ShipmentInterface $shipment, array $waybills): void
    {
        $magentoShipment = $this->shipmentRepository->get($shipmentId);
        $known = [];
        foreach ($magentoShipment->getTracks() as $track) {
            $known[] = (string)$track->getTrackNumber();
        }
        foreach (\array_diff($waybills, $known) as $waybill) {
            $track = $this->trackFactory->create()
                ->setParentId($shipmentId)
                ->setOrderId((int)$order->getEntityId())
                ->setTrackNumber($waybill)
                ->setTitle($this->getTrackTitle($order, $shipment->getCarrierCode()))
                ->setCarrierCode(Polkurier::CODE);
            $this->trackRepository->save($track);
        }
    }

    /**
     * The title customers see for a track: the label, in the order's store, of the carrier the
     * parcels go with, as the tracking popup has it. Without the carrier's settings, the shipping
     * method the order was placed with, or the carrier's code.
     */
    public function getTrackTitle(Order $order, string $carrierCode): string
    {
        $label = $this->carrierConfigProvider->getLabel($carrierCode, (int)$order->getStoreId());
        if ($label !== null) {
            return $label;
        }
        $description = (string)$order->getShippingDescription();

        return $carrierCode === '' || $carrierCode === Polkurier::methodCodeOf((string)$order->getShippingMethod())
            ? ($description !== '' ? $description : $carrierCode)
            : $carrierCode;
    }

    /**
     * Removes the shipment's Polkurier tracks for the given waybills; its other tracks stay.
     *
     * @param string[] $waybills
     */
    public function removeTracks(MagentoShipmentInterface $shipment, array $waybills): void
    {
        foreach ($shipment->getTracks() as $track) {
            if ($track->getCarrierCode() === Polkurier::CODE
                && \in_array((string)$track->getTrackNumber(), $waybills, true)
            ) {
                $this->trackRepository->delete($track);
            }
        }
    }
}
