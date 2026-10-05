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
use Polkurier\Shipping\Model\Shipment\Details\Formatter;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;
use Polkurier\Webservice\Request\Courier\GetPickupTimeMessage;
use Polkurier\Webservice\Request\Order\MultiPickupAvailabilityRequestMessage;
use Polkurier\Webservice\Request\Order\MultiPickupRequestMessage;
use Polkurier\Webservice\Request\Order\PackMessage;

/**
 * Collections: when a courier can come, and one collection for several shipments.
 */
readonly class Pickups
{
    public function __construct(
        private ShipmentGatewayInterface $gateway,
        private ShipmentRepositoryInterface $shipmentRepository,
        private ApiEventDispatcher $events,
    ) {}

    /**
     * Days and windows a carrier can collect on.
     *
     * @return array<int, array{date: string, windows: array<int, array{from: string, to: string}>}>
     * @throws LocalizedException
     */
    public function getPickupTimes(string $carrierCode, string $senderPostcode, ?string $recipientPostcode, string $shipmentType): array
    {
        $days = $this->gateway->getPickupTimes(new GetPickupTimeMessage(
            courier: $carrierCode,
            shipFrom: $senderPostcode,
            shipTo: $recipientPostcode ?: null,
            parcel: ShipmentTypeEnum::tryFrom($shipmentType),
        ));

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
     * Whether Polkurier can collect a shipment with others later, for its carrier and parcels.
     * It answers no as well when it can't tell.
     *
     * @param PackMessage[] $packs
     * @throws LocalizedException
     */
    public function isCollectiveAvailable(string $carrierCode, string $senderPostcode, string $shipmentType, array $packs): bool
    {
        return $this->gateway->isMultiPickupAvailable(new MultiPickupAvailabilityRequestMessage(
            courier: $carrierCode,
            packs: $packs,
            senderPostCode: $senderPostcode,
            shipmentType: ShipmentTypeEnum::tryFrom($shipmentType),
        ));
    }

    /**
     * Whether a shipment was placed for a later collective pickup that isn't booked yet.
     */
    public function awaitsCollective(ShipmentInterface $shipment): bool
    {
        return $shipment->getState() === ShipmentInterface::STATE_CREATED && $shipment->isMultiPickup()
            && !$shipment->isPickupBooked() && $shipment->getPolkurierNumber() !== null;
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
        foreach ($shipments as $shipment) {
            if ($this->awaitsCollective($shipment)) {
                $numbers[(string)$shipment->getPolkurierNumber()] = $shipment;
            }
        }
        if ($numbers === []) {
            throw new LocalizedException(\__('None of the selected shipments awaits a collective pickup.'));
        }
        // Whether a shipment can be collected so is Polkurier's answer for each one, given with
        // the ones it couldn't book: it depends on the carrier and the parcels, not the account.
        // Polish time, as Polkurier offers the dates, whatever PHP's own zone.
        $zone = new \DateTimeZone(Formatter::POLKURIER_TIME_ZONE);
        $request = new MultiPickupRequestMessage(
            pickupDate: new \DateTimeImmutable($date, $zone),
            pickupTimeFrom: new \DateTimeImmutable($date . ' ' . $from, $zone),
            pickupTimeTo: new \DateTimeImmutable($date . ' ' . $to, $zone),
            orders: \array_map('strval', \array_keys($numbers)),
        );
        $result = $this->gateway->bookMultiPickup($request);

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
            $this->events->dispatch(ApiEventEnum::PickupCreateAfter, null, [
                'shipments' => $bookedShipments,
                'failed' => $failed,
                'request' => $request,
                'response' => $result,
            ]);
        }

        return ['booked' => $booked, 'failed' => $failed];
    }
}
