<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Enum\Sdk\ApiEventEnum;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sdk\PolkurierTimeZone;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\Placement\SenderMapper;
use Polkurier\Webservice\Builder\Request\Courier\GetPickupTimeMessageBuilderFactory;
use Polkurier\Webservice\Builder\Request\Order\MultiPickupAvailabilityRequestMessageBuilderFactory;
use Polkurier\Webservice\Builder\Request\Order\MultiPickupRequestMessageBuilderFactory;
use Polkurier\Webservice\Entity\Order\PackItem;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;

/**
 * Collections: when a courier can come, and one collection for several shipments.
 */
readonly class PickupService
{
    public function __construct(
        private ShipmentGateway $shipmentGateway,
        private ShipmentRepositoryInterface $shipmentRepository,
        private ApiEventDispatcher $apiEventDispatcher,
        private OrderLoader $orderLoader,
        private AddressRepositoryInterface $addressRepository,
        private SenderMapper $senderMapper,
        private GetPickupTimeMessageBuilderFactory $getPickupTimeMessageBuilderFactory,
        private MultiPickupAvailabilityRequestMessageBuilderFactory $multiPickupAvailabilityRequestMessageBuilderFactory,
        private MultiPickupRequestMessageBuilderFactory $multiPickupRequestMessageBuilderFactory,
    ) {}

    /**
     * Days and windows a carrier can collect on.
     *
     * @return array<int, array{date: string, windows: array<int, array{from: string, to: string}>}>
     * @throws LocalizedException
     */
    public function getPickupTimes(
        string $carrierCode,
        string $senderPostcode,
        ?string $recipientPostcode,
        string $shipmentType,
    ): array {
        $days = $this->shipmentGateway->getPickupTimes($this->getPickupTimeMessageBuilderFactory->create()
            ->courier($carrierCode)
            ->shipFrom($senderPostcode)
            ->shipTo($recipientPostcode ?: null)
            ->parcel(ShipmentTypeEnum::tryFrom($shipmentType))
            ->build());

        $result = [];
        foreach ($days as $day) {
            // A day or a window Polkurier gives without its date or times can't be booked.
            if ($day->pickupDate === null) {
                continue;
            }
            $windows = [];
            foreach ($day->time as $window) {
                if ($window->timeFrom !== null && $window->timeTo !== null) {
                    $windows[] = [
                        'from' => $window->timeFrom->format('H:i'),
                        'to' => $window->timeTo->format('H:i'),
                    ];
                }
            }
            $result[] = ['date' => $day->pickupDate->format('Y-m-d'), 'windows' => $windows];
        }

        return $result;
    }

    /**
     * Whether Polkurier can collect a shipment with others later, for its carrier and parcels.
     * It answers no as well when it can't tell.
     *
     * @param PackItem[] $packs
     *
     * @throws LocalizedException
     */
    public function isCollectiveAvailable(
        string $carrierCode,
        string $senderPostcode,
        string $shipmentType,
        array $packs,
    ): bool {
        $request = $this->multiPickupAvailabilityRequestMessageBuilderFactory->create()
            ->courier($carrierCode)
            ->packs($packs)
            ->senderPostCode($senderPostcode)
            ->shipmentType(ShipmentTypeEnum::tryFrom($shipmentType))
            ->build();

        return $this->shipmentGateway->isMultiPickupAvailable($request);
    }

    /**
     * Whether a shipment was placed for a later collective pickup that isn't booked yet.
     */
    public function isAwaitingCollective(ShipmentInterface $shipment): bool
    {
        return $shipment->getState() === ShipmentInterface::STATE_CREATED && $shipment->isMultiPickup()
            && !$shipment->isPickupBooked() && $shipment->getPolkurierNumber() !== null;
    }

    /**
     * Of the shipments, those a collective pickup can be booked for, and the others by name.
     *
     * @param ShipmentInterface[] $shipments
     *
     * @return array{awaiting: int[], left_out: string[]} the ids of those awaiting one, and the
     *     Polkurier numbers (or ids) of the others
     */
    public function selectAwaiting(array $shipments): array
    {
        $awaiting = [];
        $leftOut = [];
        foreach ($shipments as $shipment) {
            if ($this->isAwaitingCollective($shipment)) {
                $awaiting[] = (int)$shipment->getId();
            } else {
                $leftOut[] = $shipment->getPolkurierNumber() ?? '#' . $shipment->getId();
            }
        }

        return ['awaiting' => $awaiting, 'left_out' => $leftOut];
    }

    /**
     * A collection date and window as typed, in Polish time, as Polkurier offers them whatever
     * PHP's own zone. The date must be real, and the window must end after it starts.
     *
     * @return array{date: \DateTimeImmutable, from: \DateTimeImmutable, to: \DateTimeImmutable}
     * @throws LocalizedException
     */
    public static function buildWindow(string $date, string $from, string $to): array
    {
        $day = self::parse('!Y-m-d', $date);
        $start = self::parse('!Y-m-d H:i', $date . ' ' . $from);
        $end = self::parse('!Y-m-d H:i', $date . ' ' . $to);
        if ($day === null || $start === null || $end === null || $start >= $end) {
            throw new LocalizedException(__(
                'Give the collection date as YYYY-MM-DD and the window as HH:MM, ending after it starts.',
            ));
        }

        return ['date' => $day, 'from' => $start, 'to' => $end];
    }

    /**
     * Books one collection for shipments placed for a later collective pickup.
     *
     * @param ShipmentInterface[] $shipments
     *
     * @return array{booked: string[], failed: array<string, string>}
     * @throws LocalizedException
     */
    public function bookCollective(array $shipments, string $date, string $from, string $to): array
    {
        $numbers = [];
        foreach ($shipments as $shipment) {
            if ($this->isAwaitingCollective($shipment)) {
                $numbers[(string)$shipment->getPolkurierNumber()] = $shipment;
            }
        }
        if ($numbers === []) {
            throw new LocalizedException(__('None of the selected shipments awaits a collective pickup.'));
        }
        // Polkurier answers for each shipment whether it can be collected this way, listing those
        // it couldn't book: that depends on the carrier and the parcels, not the account.
        $window = self::buildWindow($date, $from, $to);
        $request = $this->multiPickupRequestMessageBuilderFactory->create()
            ->pickupDate($window['date'])
            ->pickupTimeFrom($window['from'])
            ->pickupTimeTo($window['to'])
            ->orders(\array_map('strval', \array_keys($numbers)))
            ->build();
        $result = $this->shipmentGateway->bookMultiPickup($request);

        $booked = \array_map('strval', $result->success);
        $bookedShipments = [];
        foreach ($booked as $number) {
            if (isset($numbers[$number])) {
                $this->shipmentRepository->save($numbers[$number]->setPickupBooked(true));
                $bookedShipments[] = $numbers[$number];
            }
        }
        $failed = \array_map('strval', $result->failed);
        if ($bookedShipments !== []) {
            $this->apiEventDispatcher->dispatch(ApiEventEnum::PickupCreateAfter, null, [
                'shipments' => $bookedShipments,
                'failed' => $failed,
                'request' => $request,
                'response' => $result,
            ]);
        }

        return ['booked' => $booked, 'failed' => $failed];
    }

    /**
     * The postcode a shipment being filled in is sent from, which Polkurier's answers depend on:
     * the chosen sender's, the typed-in sender's, or the store's.
     *
     * @param array<string, mixed> $data the shipment as the screen has it: order_id,
     *        sender_address_id, new_sender_postcode
     *
     * @throws LocalizedException when there's no postcode yet, or the order isn't one to ship
     */
    public function getSenderPostcode(array $data): string
    {
        $storeId = (int)$this->orderLoader->getForPolkurier((int)($data['order_id'] ?? 0))->getStoreId();
        $senderId = (int)($data[ShipmentDraftInterface::FIELD_SENDER_ADDRESS_ID] ?? 0);
        $typedIn = ($data[ShipmentDraftInterface::FIELD_SENDER_ADDRESS_ID] ?? null) === ShipmentDraftInterface::NEW;
        $postcode = match (true) {
            // A sender typed in for the shipment: its postcode once it has one.
            $typedIn => \trim((string)($data['new_sender_postcode'] ?? '')) ?: null,
            $senderId > 0 => $this->addressRepository->get($senderId)->getPostcode(),
            default => $this->senderMapper->mapFromStore($storeId)?->getPostcode(),
        };
        if ($postcode === null || $postcode === '') {
            throw new LocalizedException($typedIn
                ? __('Fill in the sender\'s postcode first.')
                : __('Choose the sender address first.'));
        }

        return $postcode;
    }

    /**
     * Exactly as the format says: 2026-13-45 or 25:99 isn't rolled over into another date.
     */
    private static function parse(string $format, string $value): ?\DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat($format, $value, PolkurierTimeZone::getZone());
        $errors = \DateTimeImmutable::getLastErrors();

        return $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ? $parsed
            : null;
    }
}
