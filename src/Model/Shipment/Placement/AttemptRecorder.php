<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Placement;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime as DateFormat;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\ShipmentAgeChecker;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\ShipmentFactory;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Response\Order\CreatedOrderMessage;
use Polkurier\Webservice\Serializer\SerializerProviderInterface;

/**
 * Records an attempt to place a Polkurier order, and what came of it. An order has one shipment
 * however many times it's tried: the one that wasn't placed is sent again, not a row per attempt.
 * No attempt is made while an earlier one may have gone through unseen; that is checked under the
 * order's lock ({@see \Polkurier\Shipping\Model\Shipment\OrderLock}), until the new attempt is
 * recorded.
 */
readonly class AttemptRecorder
{
    public function __construct(
        private ShipmentFinder $shipmentFinder,
        private ShipmentFactory $shipmentFactory,
        private ShipmentRepositoryInterface $shipmentRepository,
        private SerializerProviderInterface $serializerProvider,
        private DraftHydrator $draftHydrator,
        private ShipmentAgeChecker $shipmentAgeChecker,
        private DateTime $dateTime,
    ) {}

    /**
     * The order's latest shipment that wasn't placed, which a new attempt sends again.
     */
    public function findNotPlaced(int $orderId): ?Shipment
    {
        $shipment = $this->shipmentFinder->findLatestForOrder($orderId, [ShipmentInterface::STATE_FAILED]);

        return $shipment instanceof Shipment ? $shipment : null;
    }

    /**
     * Refuses a new attempt while an earlier one for the same order is waiting for its answer, or
     * got none. A pending one past the wait was cut off: its outcome is unknown from then on.
     *
     * @throws LocalizedException
     */
    public function assertNothingUnresolved(int $orderId): void
    {
        $unresolved = $this->shipmentFinder->findForOrder(
            $orderId,
            [ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_PENDING],
        );
        foreach ($unresolved as $earlier) {
            if ($earlier->getState() === ShipmentInterface::STATE_PENDING
                && $earlier->getCreatedAt() !== null
                && !$this->shipmentAgeChecker->isOlderThan($earlier, ShipmentAgeChecker::ANSWER_WAIT_MINUTES)
            ) {
                throw new LocalizedException(__(
                    'A Polkurier order for this order is being placed right now. Wait a moment and reload.',
                ));
            }
            if ($earlier->getState() === ShipmentInterface::STATE_PENDING) {
                $earlier->setState(ShipmentInterface::STATE_UNKNOWN)
                    ->setLastError((string)__('The request was interrupted before an answer arrived.'));
                $this->shipmentRepository->save($earlier);
            }

            throw new LocalizedException(__(
                'Polkurier hasn\'t confirmed an earlier attempt for this order, so it may exist: %1',
                (string)$earlier->getLastError() ?: (string)__('It is checked again automatically.'),
            ));
        }
    }

    /**
     * Saves the attempt as pending before the request goes out, so an interrupted request still
     * leaves a trace, and the next request for the order finds it.
     *
     * @param Shipment|null $notPlaced the shipment sent again, from findNotPlaced()
     * @param array{qtys: array<int, float>, shipment_id: int|null, packages: array<int, array<string, mixed>>} $target
     *     what the Magento shipment ships: quantities by order item id, the existing one it goes
     *     on, and its packages
     * @param array{id: string, reserved: string|null} $externalIds from
     *     {@see \Polkurier\Shipping\Model\Shipment\Placement\ExternalIdService::buildExternalId()}
     *
     * @return array{shipment: Shipment, previous: array<string, mixed>|null} the shipment, and its
     *     data from before when a shipment that wasn't placed is being sent again, for recordRefused()
     */
    public function record(
        ?Shipment $notPlaced,
        Order $order,
        ShipmentDraftInterface $draft,
        CreateOrderMessage $message,
        array $target,
        array $externalIds,
    ): array {
        $shipment = $notPlaced ?? $this->shipmentFactory->create();
        $previous = $shipment->getId() ? $shipment->getData() : null;
        $shipment
            ->setOrderId((int)$order->getEntityId())
            ->setStoreId((int)$order->getStoreId())
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
            ->setShipItems($target['qtys'] ?: null)
            ->setTargetShipmentId($target['shipment_id'])
            ->setExternalOrderId($externalIds['id'])
            ->setReservedShipmentNumber($externalIds['reserved'])
            ->setShipPackages($target['packages'] ?: null)
            ->setRequest($this->serializerProvider->getSerializer()->serialize($message, 'json'))
            // What sending it again starts from, should it fail on Polkurier's side.
            ->setDraft($this->draftHydrator->convertToArray($draft))
            ->setLastError(null)
            // A new attempt: whatever the admin was told about the earlier one is past.
            ->setAttentionNotifiedAt(null);
        if ($previous !== null) {
            // Its age is this attempt's: that's how an interrupted attempt is recognised.
            $shipment->setCreatedAt($this->dateTime->gmtDate(DateFormat::DATETIME_PHP_FORMAT));
        }
        $this->shipmentRepository->save($shipment);

        return ['shipment' => $shipment, 'previous' => $previous];
    }

    /**
     * Refused as it is: nothing of the attempt is kept. A shipment that wasn't placed before stays
     * as it was.
     *
     * @param array<string, mixed>|null $previous as record() gave it
     */
    public function recordRefused(Shipment $shipment, ?array $previous): void
    {
        if ($previous !== null) {
            $this->shipmentRepository->save($shipment->setData($previous));
        } else {
            $this->shipmentRepository->delete($shipment);
        }
    }

    /**
     * Failed on Polkurier's side (down, busy, out of reach): kept, to be sent again as it is.
     */
    public function recordFailed(ShipmentInterface $shipment, string $reason): void
    {
        $this->shipmentRepository->save($shipment->setState(ShipmentInterface::STATE_FAILED)->setLastError($reason));
    }

    /**
     * Polkurier didn't confirm it: it may exist, and is never sent again until it's looked up in Polkurier.
     */
    public function recordUnknown(ShipmentInterface $shipment, string $reason): void
    {
        $this->shipmentRepository->save($shipment->setState(ShipmentInterface::STATE_UNKNOWN)->setLastError($reason));
    }

    public function recordPlaced(ShipmentInterface $shipment, CreatedOrderMessage $created): void
    {
        $this->shipmentRepository->save($shipment->setState(ShipmentInterface::STATE_CREATED)
            ->setPolkurierNumber($created->getOrderNumber())
            ->setWaybills(\array_values(\array_filter(\array_map('strval', $created->getLabel()))))
            ->setPriceNet($created->getPriceNet())
            ->setPriceGross($created->getPriceGross())
            ->setTrackingUrl($created->getTrackingUri())
            ->setLastError(null));
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
