<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Status;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime as DateFormat;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentDetailsInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\PickupPoint\PointFinder;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Webservice\Dto\Response\Order\OrderStatusMessage;
use Polkurier\Webservice\Entity\Order;
use Polkurier\Webservice\Entity\Order\Address;
use Polkurier\Webservice\Enum\Order\CodStatusEnum;
use Polkurier\Webservice\Enum\Order\OrderStatusCodeEnum;

/**
 * Copies what Polkurier reports about an order onto the local shipment record, with a full copy
 * of the order's details for the shipment details page.
 */
readonly class RemoteOrderApplier
{
    public const int RETURN_SETTLED_AFTER_DAYS = 14;

    /**
     * Cash-on-delivery states after which nothing more happens; null is a shipment without COD.
     */
    private const array FINAL_COD_STATUSES = [
        null,
        CodStatusEnum::Paid->value,
        CodStatusEnum::Settled->value,
        CodStatusEnum::Compensation->value,
    ];

    public function __construct(
        private DateTime $dateTime,
        private ShipmentGateway $shipmentGateway,
    ) {}

    /**
     * @throws LocalizedException when Polkurier reports the order cancelled, and can't confirm it
     */
    public function apply(Order $order, ShipmentInterface $shipment): void
    {
        $cancelled = $order->getStatusCode() === OrderStatusCodeEnum::Canceled
            && $this->isCancelled($order, $shipment);
        $waybills = [];
        foreach ($order->getWaybills() as $waybill) {
            $waybills[] = $waybill->number;
        }

        $shipment->setPolkurierNumber($order->getNumber() ?? $shipment->getPolkurierNumber())
            ->setWaybills(\array_values(\array_unique(\array_filter($waybills))))
            ->setStatusCode($order->getStatusCode()?->value)
            ->setStatusLabel($order->getStatus())
            // In UTC, as every time the module stores: Polkurier reports them in Polish time.
            // None without a waybill or before its first status: the date known so far stays.
            ->setStatusDate(
                $order->getStatusDate() !== null
                    ? self::formatUtc($order->getStatusDate())
                    : $shipment->getStatusDate(),
            )
            ->setDeliveredAt($order->getDeliveredDate() !== null ? self::formatUtc($order->getDeliveredDate()) : null)
            ->setTrackingUrl($order->getTrackingUri() ?: $shipment->getTrackingUrl())
            ->setPriceNet($order->getPriceNet())
            ->setPriceGross($order->getPriceGross())
            ->setCodAmount($order->getCod()?->getAmount() ?: null)
            ->setCodStatus($order->getCod()?->getStatus()?->value)
            ->setDetails($this->mapFromOrder($order))
            ->setDetailsSyncedAt((string)$this->dateTime->gmtDate());

        $notConfirmed = [...ShipmentInterface::UNSETTLED_STATES, ShipmentInterface::STATE_FAILED];
        if ($cancelled) {
            $shipment->setState(ShipmentInterface::STATE_CANCELLED);
        } elseif (\in_array($shipment->getState(), $notConfirmed, true)) {
            $shipment->setState(ShipmentInterface::STATE_CREATED);
        }
        $shipment->setIsFinal($this->isFinal($shipment));
    }

    /**
     * Whether nothing about the order will change any more.
     */
    public function isFinal(ShipmentInterface $shipment): bool
    {
        if ($shipment->getState() === ShipmentInterface::STATE_CANCELLED) {
            return true;
        }
        $statusDate = $shipment->getStatusDate();
        $settledBefore = $this->dateTime->gmtTimestamp() - self::RETURN_SETTLED_AFTER_DAYS * 86400;

        return match ($shipment->getStatusCode()) {
            OrderStatusCodeEnum::Delivered->value => \in_array(
                $shipment->getCodStatus(),
                self::FINAL_COD_STATUSES,
                true,
            ),
            // A return has no status for "back with the sender": it stays "on its way back" for good.
            // Once that has not changed for a while, the parcel is taken to be back.
            OrderStatusCodeEnum::Return->value => $statusDate !== null
                && \strtotime($statusDate . ' UTC') < $settledBefore,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function mapFromOrder(Order $order): array
    {
        $waybills = [];
        foreach ($order->getWaybills() as $waybill) {
            $waybills[] = [
                ShipmentDetailsInterface::FIELD_WAYBILL_NUMBER => $waybill->number,
                ShipmentDetailsInterface::FIELD_WAYBILL_IS_DEFAULT => $waybill->default,
            ];
        }
        $packs = [];
        foreach ($order->getPacks() as $pack) {
            $packs[] = [
                ShipmentDetailsInterface::FIELD_PACK_TYPE => $pack->getType()?->value,
                ShipmentDetailsInterface::FIELD_PACK_LENGTH => $pack->getLength(),
                ShipmentDetailsInterface::FIELD_PACK_WIDTH => $pack->getWidth(),
                ShipmentDetailsInterface::FIELD_PACK_HEIGHT => $pack->getHeight(),
                ShipmentDetailsInterface::FIELD_PACK_WEIGHT => $pack->getWeight(),
                ShipmentDetailsInterface::FIELD_PACK_AMOUNT => $pack->getAmount(),
                ShipmentDetailsInterface::FIELD_PACK_PRICE_NET => $pack->getPriceNet(),
                ShipmentDetailsInterface::FIELD_PACK_PRICE_GROSS => $pack->getPriceGross(),
            ];
        }
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = [
                ShipmentDetailsInterface::FIELD_ITEM_DESCRIPTION => $item->description,
                ShipmentDetailsInterface::FIELD_ITEM_TYPE => $item->type?->value,
                ShipmentDetailsInterface::FIELD_ITEM_AMOUNT => $item->amount,
                ShipmentDetailsInterface::FIELD_ITEM_VALUE_NET => $item->valueNet,
                ShipmentDetailsInterface::FIELD_ITEM_VALUE_GROSS => $item->valueGross,
            ];
        }
        $cod = $order->getCod();
        $pickup = $order->getPickup();

        return [
            ShipmentDetailsInterface::FIELD_NUMBER => $order->getNumber(),
            ShipmentDetailsInterface::FIELD_DATE => $order->getDate()
                ?->format(DateFormat::DATETIME_PHP_FORMAT),
            ShipmentDetailsInterface::FIELD_DESCRIPTION => $order->getDescription(),
            ShipmentDetailsInterface::FIELD_CARRIER => $order->getCourier(),
            ShipmentDetailsInterface::FIELD_CARRIER_NAME => $order->getCourierName(),
            ShipmentDetailsInterface::FIELD_SHIPMENT_TYPE => $order->getShipmentType()?->value,
            ShipmentDetailsInterface::FIELD_STATUS => $order->getStatus(),
            ShipmentDetailsInterface::FIELD_STATUS_CODE => $order->getStatusCode(),
            ShipmentDetailsInterface::FIELD_STATUS_DATE => $order->getStatusDate()
                ?->format(DateFormat::DATETIME_PHP_FORMAT),
            ShipmentDetailsInterface::FIELD_DELIVERED_DATE => $order->getDeliveredDate()
                ?->format(DateFormat::DATETIME_PHP_FORMAT),
            ShipmentDetailsInterface::FIELD_TRACKING_URL => $order->getTrackingUri(),
            ShipmentDetailsInterface::FIELD_WAYBILLS => $waybills,
            ShipmentDetailsInterface::FIELD_SENDER => $this->mapAddress($order->getSender()),
            ShipmentDetailsInterface::FIELD_RECIPIENT => $this->mapAddress($order->getRecipient()),
            ShipmentDetailsInterface::FIELD_COVER_ADDRESS => $this->mapAddress($order->getCoverAddress()),
            ShipmentDetailsInterface::FIELD_COD => $cod === null ? null : [
                ShipmentDetailsInterface::FIELD_COD_AMOUNT => $cod->getAmount(),
                ShipmentDetailsInterface::FIELD_COD_TYPE => $cod->getType()?->value,
                ShipmentDetailsInterface::FIELD_COD_RETURN_TYPE => $cod->getReturnType()?->value,
                ShipmentDetailsInterface::FIELD_COD_BANK_ACCOUNT => $cod->getBankAccount(),
                ShipmentDetailsInterface::FIELD_COD_STATUS => $cod->getStatus()?->value,
            ],
            ShipmentDetailsInterface::FIELD_INSURANCE => $order->getInsurance(),
            ShipmentDetailsInterface::FIELD_PICKUP => $pickup === null ? null : [
                ShipmentDetailsInterface::FIELD_PICKUP_DATE => $pickup->getPickupDate()?->format('Y-m-d'),
                ShipmentDetailsInterface::FIELD_PICKUP_TIME_FROM => $pickup->getPickupTimeFrom()?->format('H:i'),
                ShipmentDetailsInterface::FIELD_PICKUP_TIME_TO => $pickup->getPickupTimeTo()?->format('H:i'),
                ShipmentDetailsInterface::FIELD_PICKUP_NO_COURIER_ORDER => $pickup->isNoCourierOrder(),
                ShipmentDetailsInterface::FIELD_PICKUP_MULTI_PICKUP => $pickup->isMultiPickup(),
                ShipmentDetailsInterface::FIELD_PICKUP_NUMBER => $pickup->getPickupNumber(),
            ],
            ShipmentDetailsInterface::FIELD_SERVICES => \array_values(
                \array_map('strval', $order->getCourierServiceNames() ?? []),
            ),
            ShipmentDetailsInterface::FIELD_ADDITIONAL_FIELDS => \array_map(
                'strval',
                $order->getAdditionalFields(),
            ),
            ShipmentDetailsInterface::FIELD_PACKS => $packs,
            ShipmentDetailsInterface::FIELD_ITEMS => $items,
            ShipmentDetailsInterface::FIELD_PRICE_NET => $order->getPriceNet(),
            ShipmentDetailsInterface::FIELD_PRICE_GROSS => $order->getPriceGross(),
            ShipmentDetailsInterface::FIELD_REBATE_NET => $order->getRebateNet(),
            ShipmentDetailsInterface::FIELD_REBATE_GROSS => $order->getRebateGross(),
            ShipmentDetailsInterface::FIELD_VAT_RATE => $order->getVatRate(),
            ShipmentDetailsInterface::FIELD_IS_PAID => $order->isPaid(),
            ShipmentDetailsInterface::FIELD_HAS_INVOICE => $order->hasInvoice(),
            ShipmentDetailsInterface::FIELD_UNPAID_AMOUNT => $order->getUnpaidAmount(),
        ];
    }

    /**
     * The carrier's scans, newest first. Where Polkurier describes a scan no further, its
     * description is the carrier's own wording of it.
     *
     * @return array<int, array<string, string>>
     */
    public function mapTracking(OrderStatusMessage $status): array
    {
        $events = [];
        foreach ($status->tracking as $event) {
            $carrierWording = $event->trackingStatus !== $event->status ? $event->trackingStatus : '';
            $events[] = [
                ShipmentDetailsInterface::FIELD_SCAN_DATE => (string)$event->statusDate
                    ?->format(DateFormat::DATETIME_PHP_FORMAT),
                ShipmentDetailsInterface::FIELD_SCAN_STATUS_CODE => $event->statusCode->value ?? '',
                ShipmentDetailsInterface::FIELD_SCAN_STATUS => $event->status,
                ShipmentDetailsInterface::FIELD_SCAN_LOCATION => $event->location,
                ShipmentDetailsInterface::FIELD_SCAN_DESCRIPTION => $event->description ?: $carrierWording,
            ];
        }
        \usort($events, static fn(array $a, array $b): int => \strcmp(
            $b[ShipmentDetailsInterface::FIELD_SCAN_DATE],
            $a[ShipmentDetailsInterface::FIELD_SCAN_DATE],
        ));

        return $events;
    }

    /**
     * Whether an order the listing reports as cancelled is: the listing reports a cancelled waybill
     * of a live order alike.
     *
     * @throws LocalizedException
     */
    private function isCancelled(Order $order, ShipmentInterface $shipment): bool
    {
        if ($shipment->getState() === ShipmentInterface::STATE_CANCELLED) {
            return true;
        }
        $number = (string)($order->getNumber() ?? $shipment->getPolkurierNumber());
        $cancelled = $this->shipmentGateway->isCancelled($number);
        if ($cancelled === null) {
            throw new LocalizedException(__(
                'Polkurier reports order %1 as cancelled, but couldn\'t confirm it. '
                . 'It is checked again with the next status update.',
                $number,
            ));
        }

        return $cancelled;
    }

    private static function formatUtc(\DateTimeInterface $time): string
    {
        return \DateTimeImmutable::createFromInterface($time)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(DateFormat::DATETIME_PHP_FORMAT);
    }

    /**
     * @return array<string, string|null>|null
     */
    private function mapAddress(?Address $address): ?array
    {
        if ($address === null) {
            return null;
        }
        $point = $address->getPoint();

        return [
            ShipmentDetailsInterface::FIELD_ADDRESS_PERSON => $address->getPerson(),
            ShipmentDetailsInterface::FIELD_ADDRESS_COMPANY => $address->getCompany(),
            ShipmentDetailsInterface::FIELD_ADDRESS_STREET => $address->getStreet(),
            ShipmentDetailsInterface::FIELD_ADDRESS_POSTCODE => $address->getPostcode(),
            ShipmentDetailsInterface::FIELD_ADDRESS_CITY => $address->getCity(),
            ShipmentDetailsInterface::FIELD_ADDRESS_PROVINCE => $address->getProvince(),
            ShipmentDetailsInterface::FIELD_ADDRESS_COUNTRY => $address->getCountry(),
            ShipmentDetailsInterface::FIELD_ADDRESS_PHONE => $address->getPhone(),
            ShipmentDetailsInterface::FIELD_ADDRESS_EMAIL => $address->getEmail(),
            ShipmentDetailsInterface::FIELD_ADDRESS_POINT_ID => $address->getPointId() ?: null,
            ShipmentDetailsInterface::FIELD_ADDRESS_POINT_LABEL => $point !== null
                ? PointFinder::formatAddress($point)
                : null,
        ];
    }
}
