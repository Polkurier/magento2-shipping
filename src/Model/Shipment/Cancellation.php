<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\ApiEventEnum;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sdk\ShipmentGatewayInterface;
use Polkurier\Webservice\Request\Order\OrderIdMessage;

/**
 * Cancels placed Polkurier orders. The Magento shipment stays, without the order's label,
 * packages and waybills, for another Polkurier order to go on it (MagentoFollowUp::clearShipment()).
 */
readonly class Cancellation
{
    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
        private ShipmentGatewayInterface $gateway,
        private ApiEventDispatcher $events,
        private MagentoFollowUp $followUp,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function cancel(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        $number = $shipment->getPolkurierNumber();
        if ($number === null || $shipment->getState() !== ShipmentInterface::STATE_CREATED) {
            throw new LocalizedException(\__('Only a placed Polkurier order can be cancelled.'));
        }
        $request = new OrderIdMessage($number);
        $result = $this->gateway->cancelOrder($request);
        if (!$result->isCancelled()) {
            throw new LocalizedException(\__('Polkurier did not cancel order %1.', $number));
        }
        $shipment->setState(ShipmentInterface::STATE_CANCELLED)->setIsFinal(true)->setLastError(null);
        $this->shipmentRepository->save($shipment);
        $order = $this->followUp->order($shipment->getOrderId());
        $cleared = $this->followUp->clearShipment($order, $shipment);
        $this->followUp->remark($shipment->getShipmentId(), \__(
            'Polkurier order %1 cancelled. Its label and tracking numbers were removed; another Polkurier order can be placed on this shipment.',
            $number,
        ));
        $this->followUp->comment($order, $cleared !== null
            ? \__('Polkurier order %1 cancelled. Magento shipment #%2 stays, without its label and tracking numbers, for another Polkurier order.', $number, $cleared)
            : \__('Polkurier order %1 cancelled.', $number));
        $this->events->dispatch(ApiEventEnum::OrderCancelAfter, $shipment->getStoreId(), [
            'shipment' => $shipment,
            'order' => $order,
            'request' => $request,
            'response' => $result,
        ]);

        return $shipment;
    }
}
