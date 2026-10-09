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
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Webservice\Enum\Order\CodStatusEnum;
use Polkurier\Webservice\Enum\Order\OrderStatusCodeEnum;
use Polkurier\Webservice\Response\BaseAddress;
use Polkurier\Webservice\Response\Order;
use Polkurier\Webservice\Response\Order\OrderStatusMessage;

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
        $cancelled = $order->getStatusCode() === OrderStatusCodeEnum::Canceled->value
            && $this->isCancelled($order, $shipment);
        $waybills = [];
        foreach ($order->getWaybills() as $waybill) {
            $waybills[] = $waybill->getNumber();
        }

        $shipment->setPolkurierNumber($order->getNumber() ?? $shipment->getPolkurierNumber())
            ->setWaybills(\array_values(\array_unique(\array_filter($waybills))))
            ->setStatusCode($order->getStatusCode())
            ->setStatusLabel($order->getStatus())
            // In UTC, as every time the module stores: Polkurier reports them in Polish time.
            ->setStatusDate(self::utc($order->getStatusDate()))
            ->setDeliveredAt($order->getDeliveredDate() !== null ? self::utc($order->getDeliveredDate()) : null)
            ->setTrackingUrl($order->getTrackingUri() ?: $shipment->getTrackingUrl())
            ->setPriceNet($order->getPriceNet())
            ->setPriceGross($order->getPriceGross())
            ->setCodAmount($order->getCod()?->getAmount() ?: null)
            ->setCodStatus($order->getCod()?->getStatus()->value)
            ->setDetails($this->fromOrder($order))
            ->setDetailsSyncedAt((string)$this->dateTime->gmtDate());

        $notConfirmed = [
            ShipmentInterface::STATE_UNKNOWN,
            ShipmentInterface::STATE_PENDING,
            ShipmentInterface::STATE_FAILED,
        ];
        if ($cancelled) {
            $shipment->setState(ShipmentInterface::STATE_CANCELLED);
        } elseif (\in_array($shipment->getState(), $notConfirmed, true)) {
            $shipment->setState(ShipmentInterface::STATE_CREATED);
        }
        $shipment->setIsFinal($this->isFinal($shipment));
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
    public function fromOrder(Order $order): array
    {
        $waybills = [];
        foreach ($order->getWaybills() as $waybill) {
            $waybills[] = ['number' => $waybill->getNumber(), 'is_default' => $waybill->isDefault()];
        }
        $packs = [];
        foreach ($order->getPacks() as $pack) {
            $packs[] = [
                'type' => $pack->getType()->value,
                'length' => $pack->getLength(),
                'width' => $pack->getWidth(),
                'height' => $pack->getHeight(),
                'weight' => $pack->getWeight(),
                'amount' => $pack->getAmount(),
                'price_net' => $pack->getPriceNet(),
                'price_gross' => $pack->getPriceGross(),
            ];
        }
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = [
                'description' => $item->getDescription(),
                'type' => $item->getType()->value,
                'amount' => $item->getAmount(),
                'value_net' => $item->getValueNet(),
                'value_gross' => $item->getValueGross(),
            ];
        }
        $cod = $order->getCod();
        $pickup = $order->getPickup();

        return [
            'number' => $order->getNumber(),
            'date' => $order->getDate()->format(DateFormat::DATETIME_PHP_FORMAT),
            'description' => $order->getDescription(),
            'carrier' => $order->getCourier(),
            'carrier_name' => $order->getCourierName(),
            'shipment_type' => $order->getShipmentType()->value,
            'status' => $order->getStatus(),
            'status_code' => $order->getStatusCode(),
            'status_date' => $order->getStatusDate()->format(DateFormat::DATETIME_PHP_FORMAT),
            'delivered_date' => $order->getDeliveredDate()?->format(DateFormat::DATETIME_PHP_FORMAT),
            'tracking_url' => $order->getTrackingUri(),
            'waybills' => $waybills,
            'sender' => $this->address($order->getSender()),
            'recipient' => $this->address($order->getRecipient()),
            'cover_address' => $order->getCoverAddress() === null ? null : $this->address($order->getCoverAddress()),
            'cod' => $cod === null ? null : [
                'amount' => $cod->getAmount(),
                'type' => $cod->getType()->value,
                'return_type' => $cod->getReturnType()->value,
                'bank_account' => $cod->getBankAccount(),
                'status' => $cod->getStatus()->value,
            ],
            'insurance' => $order->getInsuranceAmount(),
            'pickup' => $pickup === null ? null : [
                'date' => $pickup->getPickupDate()?->format('Y-m-d'),
                'time_from' => $pickup->getPickupTimeFrom()?->format('H:i'),
                'time_to' => $pickup->getPickupTimeTo()?->format('H:i'),
                'no_courier_order' => $pickup->isNoCourierOrder(),
                'multi_pickup' => $pickup->isMultiPickup(),
                'pickup_number' => $pickup->getPickupNumber(),
            ],
            'services' => \array_values(\array_map('strval', $order->getCourierService() ?? [])),
            'additional_fields' => \array_map('strval', $order->getAdditionalFields() ?? []),
            'packs' => $packs,
            'items' => $items,
            'price_net' => $order->getPriceNet(),
            'price_gross' => $order->getPriceGross(),
            'rebate_net' => $order->getRebateNet(),
            'rebate_gross' => $order->getRebateGross(),
            'vat_rate' => $order->getVatRate(),
            'is_paid' => $order->isPaid(),
            'has_invoice' => $order->hasInvoice(),
            'unpaid_amount' => $order->getUnpaidAmount(),
        ];
    }

    /**
     * The carrier's scans, newest first. Where Polkurier describes a scan no further, its
     * description is the carrier's own wording of it.
     *
     * @return array<int, array<string, string>>
     */
    public function tracking(OrderStatusMessage $status): array
    {
        $events = [];
        foreach ($status->getTracking() as $event) {
            $carrierWording = $event->getTrackingStatus() !== $event->getStatus() ? $event->getTrackingStatus() : '';
            $events[] = [
                'date' => (string)$event->getStatusDate()?->format(DateFormat::DATETIME_PHP_FORMAT),
                'status_code' => $event->getStatusCode(),
                'status' => $event->getStatus(),
                'location' => $event->getLocation(),
                'description' => $event->getDescription() ?: $carrierWording,
            ];
        }
        \usort($events, static fn(array $a, array $b): int => \strcmp($b['date'], $a['date']));

        return $events;
    }

    private static function utc(\DateTimeInterface $time): string
    {
        return \DateTimeImmutable::createFromInterface($time)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(DateFormat::DATETIME_PHP_FORMAT);
    }

    /**
     * @return array<string, string|null>
     */
    private function address(BaseAddress $address): array
    {
        $point = $address->getPoint();

        return [
            'person' => $address->getPerson(),
            'company' => $address->getCompany(),
            'street' => $address->getStreet(),
            'postcode' => $address->getPostcode(),
            'city' => $address->getCity(),
            'province' => $address->getProvince(),
            'country' => $address->getCountry(),
            'phone' => $address->getPhone(),
            'email' => $address->getEmail(),
            'point_id' => $address->getPointId() ?: null,
            'point_label' => $point === null
                ? null
                : ($point->getAddress()
                    ?: \trim($point->getStreet() . ', ' . $point->getZip() . ' ' . $point->getCity(), ', ')),
        ];
    }
}
