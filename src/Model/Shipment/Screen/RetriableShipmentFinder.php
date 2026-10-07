<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Screen;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Placement\PlacementService;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;

/**
 * Sending a shipment that wasn't placed again: the shipment screen opens with what was sent then,
 * and why it failed. Only failures on Polkurier's side leave a shipment that wasn't placed
 * (PlacementService); it's placed by the order's next attempt, from here or not.
 */
readonly class RetriableShipmentFinder
{
    public const string PARAM = 'retry';

    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
        private ShipmentFinder $finder,
        private OrderLoader $orderLoader,
        private NativeShipmentService $nativeShipmentService,
    ) {}

    /**
     * Whether the shipment can be sent again: it wasn't placed, nothing was tried for the order
     * since, and the order still has something to send it with: items left to ship, or a Magento
     * shipment without a Polkurier order.
     */
    public function isPossible(ShipmentInterface $shipment): bool
    {
        return $this->isPossibleFor((int)$shipment->getId(), $shipment->getOrderId(), (string)$shipment->getState());
    }

    /**
     * The same, for a shipment as a grid row has it.
     */
    public function isPossibleFor(int $shipmentId, int $orderId, string $state): bool
    {
        if ($state !== ShipmentInterface::STATE_FAILED || !$this->isLatest($shipmentId, $orderId)) {
            return false;
        }
        try {
            $order = $this->orderLoader->getForPolkurier($orderId);
        } catch (LocalizedException) {
            return false;
        }

        return $order->canShip() || $this->nativeShipmentService->getFreeShipments($order) !== [];
    }

    /**
     * The shipment of the order that the screen is to start from, as the request names it; null
     * when it names none, or one that can't be sent again.
     */
    public function find(Order $order, mixed $shipmentId): ?ShipmentInterface
    {
        $id = (int)$shipmentId;
        if ($id <= 0) {
            return null;
        }
        try {
            $shipment = $this->shipmentRepository->get($id);
        } catch (NoSuchEntityException) {
            return null;
        }

        return $shipment->getOrderId() === (int)$order->getEntityId() && $this->isPossible($shipment)
            ? $shipment
            : null;
    }

    /**
     * The order's shipment that wasn't placed and can be sent again, if any: what the pages that
     * place a Polkurier order tell about, as placing one places it.
     */
    public function findForOrder(Order $order): ?ShipmentInterface
    {
        $latest = $this->finder->latestForOrder((int)$order->getEntityId());

        return $latest !== null && $this->isPossible($latest) ? $latest : null;
    }

    private function isLatest(int $shipmentId, int $orderId): bool
    {
        return (int)$this->finder->latestForOrder($orderId)?->getId() === $shipmentId;
    }
}
