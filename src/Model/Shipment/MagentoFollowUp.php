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
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Enum\ApiEventEnum;
use Polkurier\Shipping\Model\ApiEventDispatcher;
use Polkurier\Shipping\Model\Carrier\CarrierNames;
use Polkurier\Shipping\Model\OrderComments;
use Polkurier\Shipping\Model\ShipmentComments;
use Psr\Log\LoggerInterface;

/**
 * What follows on the Magento side once a Polkurier order is known: the Magento order's shipment
 * and comments, and the event that reports it. None of it may undo the Polkurier order, so
 * failures here are recorded or logged rather than thrown.
 */
readonly class MagentoFollowUp
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ShipmentRepositoryInterface $shipmentRepository,
        private MagentoShipment $magentoShipment,
        private LabelRemoval $labelRemoval,
        private OrderComments $orderComments,
        private ApiEventDispatcher $events,
        private LoggerInterface $logger,
        private ShipmentComments $shipmentComments,
        private CarrierNames $carrierNames,
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
            $this->remarkLinked($shipment);

            return null;
        } catch (\Throwable $e) {
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
     * Clears the Magento shipment of a cancelled Polkurier order of its label, packages and
     * waybills, for another Polkurier order to go on it (LabelRemoval). A failure is logged rather
     * than thrown: the Polkurier order is cancelled either way.
     *
     * @return string|null the number of the Magento shipment cleared, or null
     */
    public function clearShipment(Order $order, ShipmentInterface $shipment): ?string
    {
        try {
            return $this->labelRemoval->clear($order, $shipment);
        } catch (\Throwable $e) {
            $this->logger->error('Polkurier order cancelled but its label could not be removed from the Magento shipment', [
                'order' => $order->getIncrementId(),
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * What follows a Polkurier order's cancellation in Magento: its Magento shipment loses the
     * label and tracking numbers, to take another Polkurier order, and the order and shipment say so.
     *
     * @param bool $inPolkurier cancelled in the Polkurier panel rather than here
     *
     * @return Order the order it belongs to
     */
    public function cancelled(ShipmentInterface $shipment, bool $inPolkurier): Order
    {
        $number = (string)$shipment->getPolkurierNumber();
        $order = $this->order($shipment->getOrderId());
        $cleared = $this->clearShipment($order, $shipment);
        $this->remark($shipment->getShipmentId(), $inPolkurier
            ? \__('Polkurier order %1 was cancelled in Polkurier. Its label and tracking numbers were removed; another Polkurier order can be placed on this shipment.', $number)
            : \__('Polkurier order %1 cancelled. Its label and tracking numbers were removed; another Polkurier order can be placed on this shipment.', $number));
        $this->comment($order, match (true) {
            $inPolkurier && $cleared !== null => \__('Polkurier order %1 was cancelled in Polkurier. Magento shipment #%2 stays, without its label and tracking numbers, for another Polkurier order.', $number, $cleared),
            $inPolkurier => \__('Polkurier order %1 was cancelled in Polkurier.', $number),
            $cleared !== null => \__('Polkurier order %1 cancelled. Magento shipment #%2 stays, without its label and tracking numbers, for another Polkurier order.', $number, $cleared),
            default => \__('Polkurier order %1 cancelled.', $number),
        });

        return $order;
    }

    public function comment(Order $order, Phrase $comment): void
    {
        try {
            $this->orderComments->add($order, $comment);
        } catch (\Throwable $e) {
            $this->logger->warning('Could not add the Polkurier comment to the order', ['exception' => $e]);
        }
    }

    /**
     * An internal remark in the Magento shipment's history, never shown to the customer.
     */
    public function remark(?int $magentoShipmentId, Phrase $remark): void
    {
        if ($magentoShipmentId === null) {
            return;
        }
        try {
            $this->shipmentComments->add($magentoShipmentId, $remark);
        } catch (\Throwable $e) {
            $this->logger->warning('Could not add the Polkurier remark to the Magento shipment', ['exception' => $e]);
        }
    }

    /**
     * The remark for a Polkurier order going with a Magento shipment.
     */
    public function remarkLinked(ShipmentInterface $shipment): void
    {
        $this->remark($shipment->getShipmentId(), \__(
            'Polkurier order %1, with %2, goes with this shipment. Waybills: %3.',
            (string)$shipment->getPolkurierNumber(),
            $this->carrierNames->get($shipment->getCarrierCode()),
            \implode(', ', $shipment->getWaybills()) ?: (string)\__('not issued yet'),
        ));
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
