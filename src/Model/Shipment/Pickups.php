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
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Request\Courier\GetPickupTimeMessage;
use Polkurier\Webservice\Request\Order\MultiPickupRequestMessage;

/**
 * Collections: when a courier can come, and one collection for several shipments.
 */
class Pickups
{
    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly ErrorMessages $errorMessages,
        private readonly ApiEventDispatcher $events,
    ) {}

    /**
     * Days and windows a carrier can collect on.
     *
     * @return array<int, array{date: string, windows: array<int, array{from: string, to: string}>}>
     * @throws LocalizedException
     */
    public function getPickupTimes(int $storeId, string $carrierCode, string $senderPostcode, ?string $recipientPostcode, string $shipmentType): array
    {
        try {
            $days = $this->clientPool->courier($storeId)->getCourierPickupTime(new GetPickupTimeMessage(
                courier: $carrierCode,
                shipFrom: $senderPostcode,
                shipTo: $recipientPostcode ?: null,
                parcel: ShipmentTypeEnum::tryFrom($shipmentType),
            ));
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }

        $result = [];
        foreach ($days as $day) {
            $windows = [];
            foreach ($day->getTime() as $window) {
                $windows[] = ['from' => $window->getTimeFrom()->format('H:i'), 'to' => $window->getTimeTo()->format('H:i')];
            }
            $result[] = ['date' => $day->getPickupDate()->format('Y-m-d'), 'windows' => $windows];
        }

        return $result;
    }

    /**
     * Books one collection for shipments placed for a later collective pickup.
     *
     * @param ShipmentInterface[] $shipments
     * @return array{booked: string[], failed: array<string, string>}
     * @throws LocalizedException
     */
    public function bookCollective(array $shipments, string $date, string $from, string $to): array
    {
        $numbers = [];
        $storeId = null;
        foreach ($shipments as $shipment) {
            if ($shipment->getState() === ShipmentInterface::STATE_CREATED && $shipment->isMultiPickup()
                && !$shipment->isPickupBooked() && $shipment->getPolkurierNumber() !== null
            ) {
                $numbers[$shipment->getPolkurierNumber()] = $shipment;
                $storeId ??= $shipment->getStoreId();
            }
        }
        if ($numbers === []) {
            throw new LocalizedException(\__('None of the selected shipments awaits a collective pickup.'));
        }

        try {
            $orders = $this->clientPool->orders($storeId);
            if (!$orders->isMultiPickupAvailable()->isAvailable()) {
                throw new LocalizedException(\__('Collective pickups are not available for this Polkurier account.'));
            }
            $request = new MultiPickupRequestMessage(
                pickupDate: new \DateTimeImmutable($date),
                pickupTimeFrom: new \DateTimeImmutable($date . ' ' . $from),
                pickupTimeTo: new \DateTimeImmutable($date . ' ' . $to),
                orders: \array_map('strval', \array_keys($numbers)),
            );
            $result = $orders->createMultiOrderPickup($request);
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }

        $booked = \array_map('strval', $result->getSuccess());
        $bookedShipments = [];
        foreach ($booked as $number) {
            if (isset($numbers[$number])) {
                $this->shipmentRepository->save($numbers[$number]->setPickupBooked(true));
                $bookedShipments[] = $numbers[$number];
            }
        }
        $failed = \array_map('strval', $result->getFailed());
        if ($bookedShipments !== []) {
            $this->events->dispatch(ApiEventEnum::PickupCreateAfter, (int)$storeId, [
                'shipments' => $bookedShipments,
                'failed' => $failed,
                'request' => $request,
                'response' => $result,
            ]);
        }

        return ['booked' => $booked, 'failed' => $failed];
    }
}
