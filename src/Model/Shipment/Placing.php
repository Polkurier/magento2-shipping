<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\ApiEventEnum;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\ApiEventDispatcher;
use Polkurier\Shipping\Model\Mapper\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Sdk\PlacementUncertainException;
use Polkurier\Shipping\Model\Sdk\ShipmentGatewayInterface;
use Polkurier\Shipping\Model\ShipmentFactory;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Response\Order\CreatedOrderMessage;
use Polkurier\Webservice\Serializer\SerializerProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Places Polkurier orders. An attempt is recorded before it's sent, one order at a time per
 * Magento order, and never sent again while an earlier one may have gone through unseen.
 */
class Placing
{
    /**
     * A shipment still "pending" after this long was interrupted mid-request; its outcome is unknown.
     */
    public const int PENDING_TIMEOUT_SECONDS = 120;

    /**
     * Held per order from the check for unresolved attempts until the new one is recorded.
     */
    private const string LOCK_PREFIX = 'polkurier_place_';
    private const int LOCK_TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly OwnOrders $ownOrders,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly ShipmentFactory $shipmentFactory,
        private readonly CollectionFactory $shipmentCollectionFactory,
        private readonly CreateOrderMessageMapper $messageMapper,
        private readonly ShipmentGatewayInterface $gateway,
        private readonly MagentoShipment $magentoShipment,
        private readonly SerializerProviderInterface $serializerProvider,
        private readonly LockManagerInterface $lockManager,
        private readonly ApiEventDispatcher $events,
        private readonly ShipmentAge $age,
        private readonly MagentoFollowUp $followUp,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Places the order and ships the Magento order with it.
     *
     * @throws LocalizedException when Polkurier refused it, or it can't be sent
     */
    public function create(int $orderId, ShipmentDraftInterface $draft): ShipmentInterface
    {
        return $this->place($orderId, $draft, true);
    }

    /**
     * Places a Polkurier order for a Magento shipment that Magento is creating itself, as with a
     * shipping label from Magento's shipment page. The order is not shipped here.
     *
     * @throws LocalizedException when Polkurier refused the order, or did not confirm it
     */
    public function forMagentoShipment(int $orderId, ShipmentDraftInterface $draft): ShipmentInterface
    {
        $shipment = $this->place($orderId, $draft, false);
        if ($shipment->getState() !== ShipmentInterface::STATE_CREATED) {
            // Magento would save a shipment without tracking; it is created once the order is reconciled.
            throw new LocalizedException(\__(
                'Polkurier did not confirm the order, and it may or may not have been placed. It is checked automatically; the Magento shipment is created once it is found.',
            ));
        }

        return $shipment;
    }

    private function place(int $orderId, ShipmentDraftInterface $draft, bool $shipInMagento): ShipmentInterface
    {
        $order = $this->ownOrders->get($orderId);
        // Until the pending row below is saved, a second request for the same order (a double
        // click, another tab) would find nothing unresolved and place the order again.
        $lock = self::LOCK_PREFIX . $orderId;
        if (!$this->lockManager->lock($lock, self::LOCK_TIMEOUT_SECONDS)) {
            throw new LocalizedException(\__('A Polkurier order for this order is being placed right now. Wait a moment and reload.'));
        }
        try {
            $this->assertNothingUnresolved($orderId);
            // Checked before anything is placed: the Magento shipment is made after Polkurier accepts.
            // Magento checks its own shipment; its quantities are kept in case it has to be made later.
            $qtys = [];
            $targetId = null;
            $packages = [];
            if ($shipInMagento) {
                ['qtys' => $qtys, 'shipment_id' => $targetId, 'packages' => $packages] = $this->magentoShipment->getTarget($order, $draft);
            } else {
                foreach ($draft->getItems() as $item) {
                    $qtys[$item->getOrderItemId()] = $item->getQty();
                }
            }
            $storeId = (int)$order->getStoreId();
            $message = $this->messageMapper->map($draft, $storeId);

            $shipment = $this->shipmentFactory->create()
                ->setOrderId($orderId)
                ->setStoreId($storeId)
                ->setState(ShipmentInterface::STATE_PENDING)
                ->setCarrierCode($message->getCourier())
                ->setDescription((string)$message->getDescription())
                ->setRecipientPostcode($message->getRecipient()->getPostcode())
                ->setRecipientPhone($message->getRecipient()->getPhone())
                ->setPackCount($this->countPacks($message))
                ->setInsuranceAmount($message->getInsurance() ?: null)
                ->setCodAmount($message->getCod()?->getAmount() ?: null)
                ->setMultiPickup((bool)$message->getPickup()?->isMultiPickup())
                ->setWaybills([])
                ->setShipItems($qtys ?: null)
                ->setTargetShipmentId($targetId)
                ->setShipPackages($packages ?: null)
                ->setRequest($this->serializerProvider->getSerializer()->serialize($message, 'json'));
            // Recorded before the request goes out, so an interrupted request still leaves a trace,
            // and the next request for the order finds it.
            $this->shipmentRepository->save($shipment);
        } finally {
            $this->lockManager->unlock($lock);
        }

        try {
            $created = $this->gateway->placeOrder($message);
        } catch (PlacementUncertainException $e) {
            return $this->markUnknown($shipment, $order, $message, $e);
        } catch (LocalizedException $e) {
            $shipment->setState(ShipmentInterface::STATE_FAILED)->setLastError($e->getMessage());
            $this->shipmentRepository->save($shipment);

            throw $e;
        }

        $this->applyCreated($created, $shipment);
        $this->shipmentRepository->save($shipment);
        $this->followUp->comment($order, \__(
            'Polkurier order %1 placed with %2. Waybills: %3.',
            $shipment->getPolkurierNumber(),
            $shipment->getCarrierCode(),
            \implode(', ', $shipment->getWaybills()) ?: (string)\__('not issued yet'),
        ));
        if ($shipInMagento) {
            $this->followUp->ship($order, $shipment);
        }
        $this->events->dispatch(ApiEventEnum::OrderCreateAfter, $storeId, [
            'shipment' => $shipment,
            'order' => $order,
            'source' => $shipInMagento ? 'create' : 'label',
            'request' => $message,
            'response' => $created,
        ]);

        return $shipment;
    }

    /**
     * The order may exist. It is never sent again blindly: it has to be reconciled first.
     */
    private function markUnknown(ShipmentInterface $shipment, Order $order, CreateOrderMessage $message, PlacementUncertainException $e): ShipmentInterface
    {
        $this->logger->warning('Polkurier create_order outcome unknown', [
            'order' => $order->getIncrementId(),
            'error' => $e->getMessage(),
        ]);
        $shipment->setState(ShipmentInterface::STATE_UNKNOWN)
            ->setLastError($e->getMessage());
        $this->shipmentRepository->save($shipment);
        $this->followUp->comment($order, \__(
            'Polkurier order for %1: Polkurier did not confirm it, and it may or may not have been placed. Reconcile it before sending again.',
            $message->getCourier(),
        ));

        return $shipment;
    }

    /**
     * Refuses a new attempt while an earlier one for the same order may have succeeded unseen.
     *
     * @throws LocalizedException
     */
    private function assertNothingUnresolved(int $orderId): void
    {
        $collection = $this->shipmentCollectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::ORDER_ID, ['eq' => $orderId])
            ->addFieldToFilter(ShipmentInterface::STATE, [
                'in' => [ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_PENDING],
            ]);
        foreach ($collection->getItems() as $earlier) {
            if ($earlier->getState() === ShipmentInterface::STATE_PENDING
                && $earlier->getCreatedAt() !== null
                && !$this->age->isOlderThan($earlier, \intdiv(self::PENDING_TIMEOUT_SECONDS, 60))
            ) {
                throw new LocalizedException(\__('A Polkurier order for this order is being placed right now. Wait a moment and reload.'));
            }
            if ($earlier->getState() === ShipmentInterface::STATE_PENDING) {
                $earlier->setState(ShipmentInterface::STATE_UNKNOWN)
                    ->setLastError((string)\__('The request was interrupted before an answer arrived.'));
                $this->shipmentRepository->save($earlier);
            }

            throw new LocalizedException(\__(
                'An earlier Polkurier order for this order got no answer and may exist. Reconcile it in the Polkurier section of the order first.',
            ));
        }
    }

    private function applyCreated(CreatedOrderMessage $created, ShipmentInterface $shipment): void
    {
        $shipment->setState(ShipmentInterface::STATE_CREATED)
            ->setPolkurierNumber($created->getOrderNumber())
            ->setWaybills(\array_values(\array_filter(\array_map('strval', $created->getLabel()))))
            ->setPriceNet($created->getPriceNet())
            ->setPriceGross($created->getPriceGross())
            ->setTrackingUrl($created->getTrackingUri())
            ->setLastError(null);
    }

    private function countPacks(CreateOrderMessage $message): int
    {
        $count = 0;
        foreach ($message->getPacks() as $pack) {
            $count += $pack->getAmount();
        }

        return $count;
    }
}
