<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Polkurier\Webservice\Response\BaseAddress;
use Polkurier\Webservice\Response\Order;
use Polkurier\Webservice\Response\Order\OrderStatusMessage;

/**
 * Turns what Polkurier reports about an order into the local copy kept with the shipment: plain
 * arrays of scalars, so the copy stays readable whatever the SDK's classes become.
 */
readonly class OrderDetails
{
    private const string DATE = 'Y-m-d H:i:s';

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
            'date' => $order->getDate()->format(self::DATE),
            'description' => $order->getDescription(),
            'carrier' => $order->getCourier(),
            'carrier_name' => $order->getCourierName(),
            'shipment_type' => $order->getShipmentType()->value,
            'status' => $order->getStatus(),
            'status_code' => $order->getStatusCode(),
            'status_date' => $order->getStatusDate()->format(self::DATE),
            'delivered_date' => $order->getDeliveredDate()?->format(self::DATE),
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
                'date' => (string)$event->getStatusDate()?->format(self::DATE),
                'status_code' => $event->getStatusCode(),
                'status' => $event->getStatus(),
                'location' => $event->getLocation(),
                'description' => $event->getDescription() ?: $carrierWording,
            ];
        }
        \usort($events, static fn(array $a, array $b): int => \strcmp($b['date'], $a['date']));

        return $events;
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
            'point_label' => $point === null ? null : ($point->getAddress() ?: \trim($point->getStreet() . ', ' . $point->getZip() . ' ' . $point->getCity(), ', ')),
        ];
    }
}
