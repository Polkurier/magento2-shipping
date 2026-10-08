<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Enum\Sdk\ApiEventEnum;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Webservice\Request\Order\OrderIdMessage;

/**
 * Cancels placed Polkurier orders. The Magento shipment stays, without the order's label, packages
 * and waybills, for another Polkurier order to go on it
 * ({@see NativeFollowUpService::clearShipment()}).
 */
readonly class CancellationService
{
    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
        private ShipmentGateway $shipmentGateway,
        private ApiEventDispatcher $apiEventDispatcher,
        private NativeFollowUpService $nativeFollowUpService,
        private OrderLoader $orderLoader,
        private OrderLock $orderLock,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function cancel(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        $number = $shipment->getPolkurierNumber();
        if ($number === null || $shipment->getState() !== ShipmentInterface::STATE_CREATED) {
            throw new LocalizedException(__('Only a placed Polkurier order can be cancelled.'));
        }
        $request = new OrderIdMessage($number);
        $result = $this->shipmentGateway->cancelOrder($request);
        if (!$result->isCancelled()) {
            throw new LocalizedException(__('Polkurier did not cancel order %1.', $number));
        }
        // A refresh that saw the cancellation first has cleared its Magento shipment already.
        [$shipment, $order] = $this->orderLock->run($shipment->getOrderId(), function () use ($shipmentId): array {
            $shipment = $this->shipmentRepository->get($shipmentId);
            if ($shipment->getState() === ShipmentInterface::STATE_CANCELLED) {
                return [$shipment, $this->orderLoader->get($shipment->getOrderId())];
            }
            $shipment->setState(ShipmentInterface::STATE_CANCELLED)->setIsFinal(true)->setLastError(null);
            $this->shipmentRepository->save($shipment);

            return [$shipment, $this->nativeFollowUpService->cancelled($shipment, false)];
        });
        $this->apiEventDispatcher->dispatch(ApiEventEnum::OrderCancelAfter, $shipment->getStoreId(), [
            'shipment' => $shipment,
            'order' => $order,
            'request' => $request,
            'response' => $result,
        ]);

        return $shipment;
    }
}
