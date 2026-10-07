<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Placement;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Enum\ApiEventEnum;
use Polkurier\Shipping\Exception\PlacementUncertainException;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\OrderLock;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;
use Psr\Log\LoggerInterface;

/**
 * Places Polkurier orders. An attempt is recorded before it's sent (AttemptRecorder), one order at
 * a time per Magento order (OrderLock), and never sent again while an earlier one may have gone
 * through unseen.
 */
readonly class PlacementService
{
    public function __construct(
        private OrderLoader $orderLoader,
        private OrderLock $orderLock,
        private DraftValidator $draftValidator,
        private AttemptRecorder $attempts,
        private ExternalIdService $externalIdService,
        private CreateOrderMessageMapper $messageMapper,
        private NativeShipmentService $nativeShipmentService,
        private ShipmentGateway $gateway,
        private ErrorMessageResolver $errorMessageResolver,
        private NativeFollowUpService $followUp,
        private CarrierNameProvider $carrierNameProvider,
        private ApiEventDispatcher $events,
        private ManualEntrySaver $manualEntrySaver,
        private LoggerInterface $logger,
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
     * shipping label from Magento's shipment page, or for one it has, whose Polkurier order was
     * cancelled. The order is not shipped here.
     *
     * @param int|null $magentoShipmentId the Magento shipment it's for, null for one being created
     *
     * @throws LocalizedException when Polkurier refused the order, or did not confirm it
     */
    public function forNativeShipment(
        int $orderId,
        ShipmentDraftInterface $draft,
        ?int $magentoShipmentId = null,
    ): ShipmentInterface {
        $shipment = $this->place($orderId, $draft, false, $magentoShipmentId);
        if ($shipment->getState() !== ShipmentInterface::STATE_CREATED) {
            // Magento would save a shipment without tracking; it is created once the order is reconciled.
            throw new LocalizedException(__(
                'Polkurier did not confirm the order, and it may or may not have been placed. '
                . 'It is checked automatically; the Magento shipment is created once it is found.',
            ));
        }

        return $shipment;
    }

    private function place(
        int $orderId,
        ShipmentDraftInterface $draft,
        bool $shipInMagento,
        ?int $magentoShipmentId = null,
    ): ShipmentInterface {
        $order = $this->orderLoader->getForPolkurier($orderId);
        $storeId = (int)$order->getStoreId();
        // Held from the check for unresolved attempts until the new one is recorded: until then, a
        // second request for the same order (a double click, another tab) would find nothing
        // unresolved and place the order again.
        $this->orderLock->lock($orderId);
        try {
            $this->attempts->assertNothingUnresolved($orderId);
            $target = $this->target($order, $draft, $shipInMagento, $magentoShipmentId);
            $this->draftValidator->prepare($draft, $storeId);
            $notPlaced = $this->attempts->notPlaced($orderId);
            $externalIds = $this->externalIdService->forAttempt(
                $order,
                $notPlaced,
                $target['shipment_id'] ?? $magentoShipmentId,
            );
            $message = $this->messageMapper->map($draft, $storeId, [
                'application' => $this->externalIdService->getApplicationId(),
                'id' => $externalIds['id'],
                'number' => (string)$order->getIncrementId(),
            ]);
            $this->draftValidator->assertCarrierTakes($draft->getCarrierCode(), $message->getShipmentType());
            ['shipment' => $shipment, 'previous' => $previous] = $this->attempts->record(
                $notPlaced,
                $order,
                $draft,
                $message,
                $target,
                $externalIds,
            );
        } finally {
            $this->orderLock->unlock($orderId);
        }

        try {
            $created = $this->gateway->placeOrder($message);
        } catch (PlacementUncertainException $e) {
            return $this->markUnknown($shipment, $order, $message, $e);
        } catch (LocalizedException $e) {
            if ($this->errorMessageResolver->isTransient($e)) {
                $this->attempts->failed($shipment, $e->getMessage());
            } else {
                // The same answer every time: the reason is shown, for the shipment to be changed
                // and placed again.
                $this->attempts->refused($shipment, $previous);
            }

            throw $e;
        }

        // Once saved as placed, a refresh or the admin may ship it in Magento too: not before this does.
        $this->orderLock->run($orderId, function () use ($created, $shipment, $order, $shipInMagento): void {
            $this->attempts->placed($shipment, $created);
            $this->followUp->comment($order, __(
                'Polkurier order %1 placed with %2. Waybills: %3.',
                $shipment->getPolkurierNumber(),
                $this->carrierNameProvider->get($shipment->getCarrierCode()),
                \implode(', ', $shipment->getWaybills()) ?: (string)__('not issued yet'),
            ));
            if ($shipInMagento) {
                $this->followUp->ship($order, $shipment);
            }
        });
        $this->events->dispatch(ApiEventEnum::OrderCreateAfter, $storeId, [
            'shipment' => $shipment,
            'order' => $order,
            'source' => $shipInMagento ? 'create' : 'label',
            'request' => $message,
            'response' => $created,
        ]);
        // What was typed in to be kept goes once the shipment is placed, never before.
        $this->manualEntrySaver->keep($draft);

        return $shipment;
    }

    /**
     * What the Magento shipment ships, checked before anything is placed: no item goes with two
     * Polkurier orders that stand, as each Magento shipment has at most one, and a new one ships
     * only what the order has left.
     *
     * @return array{qtys: array<int, float>, shipment_id: int|null, packages: array<int, array<string, mixed>>}
     * @throws LocalizedException
     */
    private function target(
        Order $order,
        ShipmentDraftInterface $draft,
        bool $shipInMagento,
        ?int $magentoShipmentId,
    ): array {
        // Made by the module after Polkurier accepts: checked here, its quantities kept in case it
        // has to be made later.
        if ($shipInMagento) {
            return $this->nativeShipmentService->getTarget($order, $draft);
        }
        if ($magentoShipmentId !== null) {
            $this->nativeShipmentService->assertFree($order, $magentoShipmentId);
        }
        // A Magento shipment being created is checked by Magento against what the order has left,
        // and its items already count as shipped on the order: they're taken as they are.
        $qtys = [];
        foreach ($draft->getItems() as $item) {
            $qtys[$item->getOrderItemId()] = $item->getQty();
        }

        return ['qtys' => $qtys, 'shipment_id' => null, 'packages' => []];
    }

    /**
     * The order may exist. It is never sent again blindly: it has to be reconciled first.
     */
    private function markUnknown(
        ShipmentInterface $shipment,
        Order $order,
        CreateOrderMessage $message,
        PlacementUncertainException $e,
    ): ShipmentInterface {
        $this->logger->warning('Polkurier create_order outcome unknown', [
            'order' => $order->getIncrementId(),
            'exception' => $e,
        ]);
        $this->attempts->unknown($shipment, $e->getMessage());
        $this->followUp->comment($order, __(
            'Polkurier order for %1: Polkurier did not confirm it, and it may or may not have been placed. '
            . 'Reconcile it before sending again.',
            $this->carrierNameProvider->get((string)$message->getCourier()),
        ));

        return $shipment;
    }
}
