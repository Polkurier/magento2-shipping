<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\ApiEventEnum;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\ApiEventDispatcher;
use Polkurier\Shipping\Model\OrderComments;
use Psr\Log\LoggerInterface;

/**
 * What follows on the Magento side once a Polkurier order is known: the Magento order's shipment
 * and comments, and the event that reports it. None of it may undo the Polkurier order, so
 * failures here are recorded or logged rather than thrown.
 */
class MagentoFollowUp
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly MagentoShipment $magentoShipment,
        private readonly MagentoShipmentRemoval $removal,
        private readonly OrderComments $orderComments,
        private readonly ApiEventDispatcher $events,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function order(int $orderId): Order
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(\__('The order no longer exists.'), $e);
        }
        if (!$order instanceof Order) {
            throw new LocalizedException(\__('The order cannot be shipped with Polkurier.'));
        }

        return $order;
    }

    /**
     * Ships the Magento order with the Polkurier order's waybills. A failure is recorded on the
     * shipment, to be tried again, rather than thrown.
     *
     * @return string|null why the Magento shipment could not be created, or null
     */
    public function ship(Order $order, ShipmentInterface $shipment): ?string
    {
        try {
            $shipmentId = $this->magentoShipment->ship(
                $order,
                $shipment,
                $shipment->getShipItems(),
                $shipment->getTargetShipmentId(),
                $shipment->getShipPackages(),
            );
            $this->shipmentRepository->save($shipment->setShipmentId($shipmentId));

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Polkurier order placed but the Magento shipment failed', [
                'order' => $order->getIncrementId(),
                'exception' => $e,
            ]);
            // Still unlinked: another Polkurier order may have taken the Magento shipment meanwhile.
            $shipment->setShipmentId(null)
                ->setLastError((string)\__('The Magento shipment could not be created: %1', $e->getMessage()));
            $this->shipmentRepository->save($shipment);

            return $e->getMessage();
        }
    }

    /**
     * Removes the Magento shipment of a cancelled Polkurier order, so its items can be shipped
     * again. A failure is logged rather than thrown: the Polkurier order is cancelled either way.
     *
     * @return string|null the number of the Magento shipment removed, or null
     */
    public function removeShipment(Order $order, ShipmentInterface $shipment): ?string
    {
        $magentoShipmentId = $shipment->getShipmentId();
        if ($magentoShipmentId === null) {
            return null;
        }
        try {
            $number = $this->removal->remove($order, $magentoShipmentId);
        } catch (\Exception $e) {
            $this->logger->error('Polkurier order cancelled but its Magento shipment could not be removed', [
                'order' => $order->getIncrementId(),
                'exception' => $e,
            ]);

            return null;
        }
        if ($number !== null) {
            $shipment->setShipmentId(null);
        }

        return $number;
    }

    public function comment(Order $order, Phrase $comment): void
    {
        try {
            $this->orderComments->add($order, $comment);
        } catch (\Exception $e) {
            $this->logger->warning('Could not add the Polkurier comment to the order', ['exception' => $e]);
        }
    }

    /**
     * An unconfirmed attempt turned out to have been placed: reported like any placed order.
     *
     * @param string $source how it was found: "reconcile" or "adopt"
     */
    public function linked(ShipmentInterface $shipment, Order $order, string $source): void
    {
        $this->events->dispatch(ApiEventEnum::OrderCreateAfter, $shipment->getStoreId(), [
            'shipment' => $shipment,
            'order' => $order,
            'source' => $source,
            'request' => null,
            'response' => null,
        ]);
    }
}
