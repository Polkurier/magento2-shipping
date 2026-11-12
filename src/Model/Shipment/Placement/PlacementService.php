<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Placement;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Enum\Sdk\ApiEventEnum;
use Polkurier\Shipping\Exception\PlacementUncertainException;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sdk\ApplicationIdProvider;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\OrderLock;
use Polkurier\Shipping\Model\Shipment\Status\ReconciliationService;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;

/**
 * Places Polkurier orders. An attempt is recorded before it's sent
 * ({@see \Polkurier\Shipping\Model\Shipment\Placement\AttemptRecorder}), one order at a time per
 * Magento order ({@see OrderLock}), and never sent again while an earlier one may have gone through
 * unseen.
 */
readonly class PlacementService
{
    public function __construct(
        private OrderLoader $orderLoader,
        private OrderLock $orderLock,
        private DraftValidator $draftValidator,
        private AttemptRecorder $attemptRecorder,
        private ExternalIdService $externalIdService,
        private ApplicationIdProvider $applicationIdProvider,
        private CreateOrderMessageMapper $createOrderMessageMapper,
        private NativeShipmentService $nativeShipmentService,
        private ShipmentGateway $shipmentGateway,
        private ErrorMessageResolver $errorMessageResolver,
        private NativeFollowUpService $nativeFollowUpService,
        private ApiEventDispatcher $apiEventDispatcher,
        private ManualEntrySaver $manualEntrySaver,
        private ReconciliationService $reconciliationService,
        private Logger $logger,
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
     * shipping label from Magento's shipment page, or for an existing one whose Polkurier order was
     * cancelled. The order is not shipped here.
     *
     * @param int|null $magentoShipmentId the Magento shipment it's for, null for one being created
     *
     * @throws LocalizedException when Polkurier refused the order, or did not confirm it
     */
    public function placeForNativeShipment(
        int $orderId,
        ShipmentDraftInterface $draft,
        ?int $magentoShipmentId = null,
    ): ShipmentInterface {
        $shipment = $this->place($orderId, $draft, false, $magentoShipmentId);
        if ($shipment->getState() !== ShipmentInterface::STATE_CREATED) {
            // Magento would save a shipment without tracking; it is completed once the order is found.
            throw new LocalizedException(__(
                'Polkurier did not confirm the order, and it may or may not have been placed. '
                . 'It is checked automatically; the Magento shipment is completed once it is found.',
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
        // An earlier attempt Polkurier didn't confirm is looked up first: found placed, cancelled
        // or not placed, it no longer holds this one back.
        $this->reconciliationService->settleForOrder($orderId);
        // Held from the check for unresolved attempts until the new one is recorded: until then, a
        // second request for the same order (a double click, another tab) would find nothing
        // unresolved and place the order again.
        ['message' => $message, 'shipment' => $shipment, 'previous' => $previous] = $this->orderLock->run(
            $orderId,
            fn(): array => $this->recordAttempt($orderId, $order, $draft, $shipInMagento, $magentoShipmentId),
        );

        try {
            $created = $this->shipmentGateway->placeOrder($message);
        } catch (PlacementUncertainException $e) {
            return $this->markUnknown($shipment, $order, $e);
        } catch (LocalizedException $e) {
            if ($this->errorMessageResolver->isTransient($e)) {
                $this->attemptRecorder->recordFailed($shipment, $e->getMessage());
            } else {
                // The same answer every time: the reason is shown, for the shipment to be changed
                // and placed again.
                $this->attemptRecorder->recordRefused($shipment, $previous);
            }

            throw $e;
        }

        // Under the lock: once saved as placed, a refresh or the admin may ship it too, but not
        // before this does.
        $this->orderLock->run($orderId, function () use ($created, $shipment, $order, $shipInMagento): void {
            $this->attemptRecorder->recordPlaced($shipment, $created);
            $this->nativeFollowUpService->notePlaced($order, $shipment);
            if ($shipInMagento) {
                $this->nativeFollowUpService->ship($order, $shipment);
            }
        });
        $this->apiEventDispatcher->dispatch(ApiEventEnum::OrderCreateAfter, $storeId, [
            'shipment' => $shipment,
            'order' => $order,
            'source' => $shipInMagento ? 'create' : 'label',
            'request' => $message,
            'response' => $created,
        ]);
        // Entries typed in to be kept are saved once the shipment is placed, never before.
        $this->manualEntrySaver->keep($draft);

        return $shipment;
    }

    /**
     * Checks the attempt can be made, and records it.
     *
     * @return array{message: CreateOrderMessage, shipment: Shipment, previous: array<string, mixed>|null}
     * @throws LocalizedException when it can't be made
     */
    private function recordAttempt(
        int $orderId,
        Order $order,
        ShipmentDraftInterface $draft,
        bool $shipInMagento,
        ?int $magentoShipmentId,
    ): array {
        $storeId = (int)$order->getStoreId();
        $this->attemptRecorder->assertNothingUnresolved($orderId);
        $target = $this->resolveTarget($order, $draft, $shipInMagento, $magentoShipmentId);
        $this->draftValidator->prepare($draft, $storeId);
        $notPlaced = $this->attemptRecorder->findNotPlaced($orderId);
        $externalIds = $this->externalIdService->buildExternalId($order, $notPlaced, $target['shipment_id']);
        $message = $this->createOrderMessageMapper->map($draft, $storeId, [
            'application' => $this->applicationIdProvider->get(),
            'id' => $externalIds['id'],
            'number' => (string)$order->getIncrementId(),
        ]);
        $this->draftValidator->assertCarrierTakes($draft->getCarrierCode(), $message->getShipmentType());

        return ['message' => $message] + $this->attemptRecorder->record(
            $notPlaced,
            $order,
            $draft,
            $message,
            $target,
            $externalIds,
        );
    }

    /**
     * What the Magento shipment ships, checked before anything is placed. No item goes with two
     * standing Polkurier orders, as each Magento shipment has at most one, and a new Magento
     * shipment ships only what the order has left.
     *
     * @return array{qtys: array<int, float>, shipment_id: int|null, packages: array<int, array<string, mixed>>}
     * @throws LocalizedException
     */
    private function resolveTarget(
        Order $order,
        ShipmentDraftInterface $draft,
        bool $shipInMagento,
        ?int $magentoShipmentId,
    ): array {
        // Created by the module after Polkurier accepts: checked here, and its quantities are kept
        // in case it has to be created later.
        if ($shipInMagento) {
            return $this->nativeShipmentService->getTarget($order, $draft);
        }
        if ($magentoShipmentId !== null) {
            // An existing one, recorded so that an order found placed later goes on it.
            $this->nativeShipmentService->assertFree($order, $magentoShipmentId);

            return ['qtys' => [], 'shipment_id' => $magentoShipmentId, 'packages' => []];
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
     * The order may exist. It is never sent again blindly: it's looked up in Polkurier first.
     */
    private function markUnknown(
        ShipmentInterface $shipment,
        Order $order,
        PlacementUncertainException $e,
    ): ShipmentInterface {
        $this->logger->warning('Polkurier create_order outcome unknown', [
            'order' => $order->getIncrementId(),
            'exception' => $e,
        ]);
        // Not noted on the order: it's settled automatically, and the outcome is noted then.
        $this->attemptRecorder->recordUnknown($shipment, $e->getMessage());

        return $shipment;
    }
}
