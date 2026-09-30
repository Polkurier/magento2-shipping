<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface as PolkurierShipmentRepository;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;

/**
 * Takes back the Magento shipment of a cancelled Polkurier order, so its items can be shipped
 * again. Magento can't cancel a shipment, so it's deleted, tracking numbers and all, and what it
 * shipped goes back to the order's items to ship; a complete order is processing again.
 */
class MagentoShipmentRemoval
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CollectionFactory $shipmentCollectionFactory,
        private readonly PolkurierShipmentRepository $polkurierShipmentRepository,
        private readonly ResourceConnection $resource,
    ) {}

    /**
     * @return string|null the number of the Magento shipment removed; null when it stays, because
     *     another Polkurier order holds it or it isn't the order's
     * @throws \Exception when it can't be removed; nothing is changed then
     */
    public function remove(Order $order, int $magentoShipmentId): ?string
    {
        $magentoShipment = $this->shipmentRepository->get($magentoShipmentId);
        if ((int)$magentoShipment->getOrderId() !== (int)$order->getEntityId()) {
            return null;
        }
        $linked = $this->shipmentCollectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::SHIPMENT_ID, ['eq' => $magentoShipmentId]);
        foreach ($linked->getItems() as $polkurier) {
            if (\in_array($polkurier->getState(), MagentoShipment::HOLDING_STATES, true)) {
                return null;
            }
        }

        $connection = $this->resource->getConnection('sales');
        $connection->beginTransaction();
        try {
            foreach ($magentoShipment->getItems() as $item) {
                $orderItem = $order->getItemById($item->getOrderItemId());
                if ($orderItem !== null) {
                    $orderItem->setQtyShipped(\max(0.0, (float)$orderItem->getQtyShipped() - (float)$item->getQty()));
                }
            }
            foreach ($linked->getItems() as $polkurier) {
                $this->polkurierShipmentRepository->save($polkurier->setShipmentId(null));
            }
            $number = (string)$magentoShipment->getIncrementId();
            $this->shipmentRepository->delete($magentoShipment);
            if ($order->getState() === Order::STATE_COMPLETE) {
                $order->setState(Order::STATE_PROCESSING)
                    ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
            }
            $this->orderRepository->save($order);
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        return $number;
    }
}
