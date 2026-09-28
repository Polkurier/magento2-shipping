<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Polkurier\Shipping\Api\ApiEventEnum;
use Polkurier\Shipping\Model\ApiEventDispatcher;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Mapper\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Shipping\Model\ShipmentFactory;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Exception\MalformedResponseException;
use Polkurier\Webservice\Exception\WebserviceException;
use Polkurier\Webservice\Enum\Http\ApiErrorCodeEnum;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Request\Order\OrderIdMessage;
use Polkurier\Webservice\Response\Order\CreatedOrderMessage;
use Polkurier\Webservice\Serializer\SerializerProviderInterface;
use Psr\Log\LoggerInterface;

class ShipmentManagement implements ShipmentManagementInterface
{
    /**
     * How long after an unconfirmed attempt an order that Polkurier does not list counts as not
     * placed, rather than not listed yet.
     */
    public const int NOT_PLACED_AFTER_MINUTES = 15;

    /**
     * A shipment still "pending" after this long was interrupted mid-request; its outcome is unknown.
     */
    public const int PENDING_TIMEOUT_SECONDS = 120;

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly ShipmentFactory $shipmentFactory,
        private readonly CollectionFactory $shipmentCollectionFactory,
        private readonly CreateOrderMessageMapper $messageMapper,
        private readonly DefaultDraftBuilder $draftBuilder,
        private readonly ClientPool $clientPool,
        private readonly MagentoShipment $magentoShipment,
        private readonly Reconciler $reconciler,
        private readonly OrderSnapshot $snapshot,
        private readonly ErrorMessages $errorMessages,
        private readonly SerializerProviderInterface $serializerProvider,
        private readonly LoggerInterface $logger,
        private readonly ApiEventDispatcher $events,
    ) {}

    public function getDefaultDraft(int $orderId): ShipmentDraftInterface
    {
        return $this->draftBuilder->createFromOrder($this->getOrder($orderId));
    }

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
    public function placeForMagentoShipment(int $orderId, ShipmentDraftInterface $draft): ShipmentInterface
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
        $order = $this->getOrder($orderId);
        $this->assertNothingUnresolved($orderId);
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
            ->setRequest($this->serializerProvider->getSerializer()->serialize($message, 'json'));
        // Recorded before the request goes out, so an interrupted request still leaves a trace.
        $this->shipmentRepository->save($shipment);

        try {
            $created = $this->clientPool->orders($storeId)->createOrder($message);
        } catch (ConnectionException | MalformedResponseException $e) {
            return $this->markUnknown($shipment, $order, $message, $e);
        } catch (WebserviceException $e) {
            if ($this->mayHaveBeenPlaced($e)) {
                return $this->markUnknown($shipment, $order, $message, $e);
            }
            $error = $this->errorMessages->describe($e);
            $shipment->setState(ShipmentInterface::STATE_FAILED)->setLastError($error);
            $this->shipmentRepository->save($shipment);

            throw new LocalizedException(\__('%1', $error), $e);
        } catch (PolkurierException $e) {
            $error = $this->errorMessages->describe($e);
            $shipment->setState(ShipmentInterface::STATE_FAILED)->setLastError($error);
            $this->shipmentRepository->save($shipment);

            throw new LocalizedException(\__('%1', $error), $e);
        }

        $this->applyCreated($created, $shipment);
        $this->shipmentRepository->save($shipment);
        $this->comment($order, \__(
            'Polkurier order %1 placed with %2. Waybills: %3.',
            $shipment->getPolkurierNumber(),
            $shipment->getCarrierCode(),
            \implode(', ', $shipment->getWaybills()) ?: (string)\__('not issued yet'),
        ));
        if ($shipInMagento) {
            $this->shipMagentoOrder($order, $shipment);
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
     * Placing an order isn't idempotent, and only a refusal with a specific reason (validation,
     * carrier, account) is known to have created nothing. Anything vaguer may hide a placed order.
     */
    private function mayHaveBeenPlaced(WebserviceException $e): bool
    {
        $code = $e->getErrorMessage()->getErrorCode();

        return $code === null || $code === '' || $code === ApiErrorCodeEnum::UnknownError->value;
    }

    /**
     * The order may exist. It is never sent again blindly: it has to be reconciled first.
     */
    private function markUnknown(ShipmentInterface $shipment, Order $order, CreateOrderMessage $message, PolkurierException $e): ShipmentInterface
    {
        $this->logger->warning('Polkurier create_order outcome unknown', [
            'order' => $order->getIncrementId(),
            'error' => $this->errorMessages->describe($e),
        ]);
        $shipment->setState(ShipmentInterface::STATE_UNKNOWN)
            ->setLastError($this->errorMessages->describe($e));
        $this->shipmentRepository->save($shipment);
        $this->comment($order, \__(
            'Polkurier order for %1: Polkurier did not confirm it, and it may or may not have been placed. Reconcile it before sending again.',
            $message->getCourier(),
        ));

        return $shipment;
    }

    public function reconcile(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        if (!\in_array($shipment->getState(), [ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_PENDING], true)) {
            throw new LocalizedException(\__('Only a shipment whose outcome is unknown can be reconciled.'));
        }

        try {
            $candidates = $this->reconciler->findCandidates($shipment);
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }

        $order = $this->getOrder($shipment->getOrderId());
        $shipment->setStatusCheckedAt(\gmdate('Y-m-d H:i:s'));
        if ($candidates === []) {
            if ($this->isOlderThan($shipment, self::NOT_PLACED_AFTER_MINUTES)) {
                $shipment->setState(ShipmentInterface::STATE_FAILED)
                    ->setLastError((string)\__('Not found in Polkurier: the order was not placed. It is safe to send it again.'));
            } else {
                $shipment->setState(ShipmentInterface::STATE_UNKNOWN)
                    ->setLastError((string)\__('Not found in Polkurier yet. It is checked again automatically.'));
            }
            $this->shipmentRepository->save($shipment);

            return $shipment;
        }

        // Several matches mean the order went through more than once: keep the first, report the rest.
        \usort($candidates, static fn($a, $b): int => $a->getDate() <=> $b->getDate());
        $adopted = \array_shift($candidates);
        $duplicates = \array_map(static fn($candidate): string => (string)$candidate->getNumber(), $candidates);
        $this->snapshot->apply($adopted, $shipment);
        $shipment->setState(ShipmentInterface::STATE_CREATED)->setLastError($duplicates === [] ? null : (string)\__(
            'Polkurier also has %1 for this order, probably duplicates. Check them in the Polkurier panel and cancel the ones not needed.',
            \implode(', ', $duplicates),
        ));
        $this->shipmentRepository->save($shipment);
        $this->comment($order, \__('Polkurier order %1 found and linked after an unconfirmed request.', $shipment->getPolkurierNumber()));
        if ($duplicates !== []) {
            $this->comment($order, \__(
                'Polkurier also has %1 for this order, probably duplicates. Check them in the Polkurier panel and cancel the ones not needed.',
                \implode(', ', $duplicates),
            ));
        }
        $this->shipMagentoOrder($order, $shipment);
        $this->dispatchLinked($shipment, $order, 'reconcile');

        return $shipment;
    }

    /**
     * Links a shipment whose outcome is unknown to a Polkurier order the merchant identified.
     *
     * @throws LocalizedException
     */
    public function adopt(int $shipmentId, string $polkurierNumber): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        if ($shipment->getState() !== ShipmentInterface::STATE_UNKNOWN) {
            throw new LocalizedException(\__('Only a shipment whose outcome is unknown can be linked by hand.'));
        }
        try {
            $remote = $this->reconciler->findByNumber($shipment, \trim($polkurierNumber));
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }
        $this->snapshot->apply($remote, $shipment);
        $shipment->setState(ShipmentInterface::STATE_CREATED)->setLastError(null);
        $this->shipmentRepository->save($shipment);
        $order = $this->getOrder($shipment->getOrderId());
        $this->comment($order, \__('Polkurier order %1 linked by hand after an unconfirmed request.', $shipment->getPolkurierNumber()));
        $this->shipMagentoOrder($order, $shipment);
        $this->dispatchLinked($shipment, $order, 'adopt');

        return $shipment;
    }

    public function refreshStatus(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        $number = $shipment->getPolkurierNumber();
        if ($number === null) {
            throw new LocalizedException(\__('This shipment has no Polkurier order to read.'));
        }
        try {
            $remote = $this->reconciler->findByNumber($shipment, $number);
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }
        $before = $shipment->getWaybills();
        $this->snapshot->apply($remote, $shipment);
        $shipment->setLastError(null)->setStatusCheckedAt(\gmdate('Y-m-d H:i:s'));
        $this->shipmentRepository->save($shipment);
        if ($shipment->getShipmentId() === null && $shipment->getState() === ShipmentInterface::STATE_CREATED) {
            // Placed, but the Magento shipment failed back then: try again with every refresh.
            $this->shipMagentoOrder($this->getOrder($shipment->getOrderId()), $shipment);

            return $shipment;
        }

        if ($shipment->getShipmentId() !== null && \array_diff($shipment->getWaybills(), $before) !== []) {
            $this->magentoShipment->addTracks(
                $this->getOrder($shipment->getOrderId()),
                $shipment->getShipmentId(),
                $shipment,
                $shipment->getWaybills(),
            );
        }

        return $shipment;
    }

    public function shipOrder(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        if ($shipment->getState() !== ShipmentInterface::STATE_CREATED) {
            throw new LocalizedException(\__('Only a placed Polkurier order can be shipped in Magento.'));
        }
        if ($shipment->getShipmentId() !== null) {
            throw new LocalizedException(\__('The Magento order is already shipped with this Polkurier order.'));
        }
        $error = $this->shipMagentoOrder($this->getOrder($shipment->getOrderId()), $shipment);
        if ($error !== null) {
            throw new LocalizedException(\__('The Magento shipment could not be created: %1', $error));
        }
        // The error recorded was that the Magento shipment failed; it is resolved now.
        $this->shipmentRepository->save($shipment->setLastError(null));

        return $shipment;
    }

    public function cancel(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        $number = $shipment->getPolkurierNumber();
        if ($number === null || $shipment->getState() !== ShipmentInterface::STATE_CREATED) {
            throw new LocalizedException(\__('Only a placed Polkurier order can be cancelled.'));
        }
        try {
            $request = new OrderIdMessage($number);
            $result = $this->clientPool->orders($shipment->getStoreId())->cancelOrder($request);
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }
        if (!$result->isCancelled()) {
            throw new LocalizedException(\__('Polkurier did not cancel order %1.', $number));
        }
        $shipment->setState(ShipmentInterface::STATE_CANCELLED)->setIsFinal(true)->setLastError(null);
        $this->shipmentRepository->save($shipment);
        $order = $this->getOrder($shipment->getOrderId());
        $this->comment($order, \__(
            'Polkurier order %1 cancelled. The Magento shipment and its tracking numbers were kept; remove them by hand if needed.',
            $number,
        ));
        $this->events->dispatch(ApiEventEnum::OrderCancelAfter, $shipment->getStoreId(), [
            'shipment' => $shipment,
            'order' => $order,
            'request' => $request,
            'response' => $result,
        ]);

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
                && \strtotime((string)$earlier->getCreatedAt()) > \time() - self::PENDING_TIMEOUT_SECONDS
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

    /**
     * The Polkurier order stands even if the Magento side fails; the merchant is told, not blocked.
     */
    private function isOlderThan(ShipmentInterface $shipment, int $minutes): bool
    {
        $createdAt = $shipment->getCreatedAt();

        return $createdAt !== null && \strtotime($createdAt . ' UTC') < \time() - $minutes * 60;
    }

    /**
     * An unconfirmed attempt turned out to have been placed: reported like any placed order.
     */
    private function dispatchLinked(ShipmentInterface $shipment, Order $order, string $source): void
    {
        $this->events->dispatch(ApiEventEnum::OrderCreateAfter, $shipment->getStoreId(), [
            'shipment' => $shipment,
            'order' => $order,
            'source' => $source,
            'request' => null,
            'response' => null,
        ]);
    }

    /**
     * Ships the Magento order with the Polkurier order's waybills. The Polkurier order exists
     * whatever happens here, so a failure is recorded on the shipment rather than thrown.
     *
     * @return string|null why the Magento shipment could not be created, or null
     */
    private function shipMagentoOrder(Order $order, ShipmentInterface $shipment): ?string
    {
        try {
            $shipmentId = $this->magentoShipment->ship($order, $shipment);
            if ($shipmentId !== null) {
                $shipment->setShipmentId($shipmentId);
                $this->shipmentRepository->save($shipment);
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Polkurier order placed but the Magento shipment failed', [
                'order' => $order->getIncrementId(),
                'exception' => $e,
            ]);
            $shipment->setLastError((string)\__('The Magento shipment could not be created: %1', $e->getMessage()));
            $this->shipmentRepository->save($shipment);

            return $e->getMessage();
        }
    }

    private function countPacks(CreateOrderMessage $message): int
    {
        $count = 0;
        foreach ($message->getPacks() as $pack) {
            $count += $pack->getAmount();
        }

        return $count;
    }

    private function comment(Order $order, \Magento\Framework\Phrase $comment): void
    {
        try {
            $order->addCommentToStatusHistory((string)$comment);
            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            $this->logger->warning('Could not add the Polkurier comment to the order', ['exception' => $e]);
        }
    }

    /**
     * @throws LocalizedException
     */
    private function getOrder(int $orderId): Order
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
}
