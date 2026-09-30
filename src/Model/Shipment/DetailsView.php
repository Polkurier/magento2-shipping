<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment\Details\Formatter;
use Polkurier\Shipping\Model\Shipment\Details\Links;
use Polkurier\Shipping\Model\Shipment\Details\OptionLabels;
use Polkurier\Shipping\Model\Shipment\Details\SentOrder;

/**
 * The shipment details page as sections of labelled rows and tables, ready to show: the local
 * record, the local copy of the Polkurier order (or, until Polkurier has been read, the order as
 * it was sent) and the carrier's scans.
 */
class DetailsView
{
    public function __construct(
        private readonly Links $links,
        private readonly OptionLabels $labels,
        private readonly Formatter $format,
        private readonly SentOrder $sentOrder,
    ) {}

    /**
     * @param string[] $errors why part of it could not be read just now
     * @return array<string, mixed>
     */
    public function build(ShipmentInterface $shipment, array $errors = []): array
    {
        $details = $shipment->getDetails();
        $fromPolkurier = $details !== [];
        if (!$fromPolkurier) {
            $details = $this->sentOrder->read((string)$shipment->getRequest());
        }

        $sections = [
            [
                'title' => (string)\__('Shipment'),
                'items' => [
                    ['title' => (string)\__('Polkurier order'), 'rows' => $this->orderRows($shipment, $details)],
                    ['title' => (string)\__('Delivery'), 'rows' => $this->deliveryRows($shipment, $details)],
                ],
            ],
            [
                'title' => (string)\__('Addresses'),
                'items' => \array_values(\array_filter([
                    ['title' => (string)\__('Sender'), 'rows' => $this->addressRows($details['sender'] ?? null)],
                    ['title' => (string)\__('Recipient'), 'rows' => $this->addressRows($details['recipient'] ?? null)],
                    empty($details['cover_address']) ? null
                        : ['title' => (string)\__('Sender on the label'), 'rows' => $this->addressRows($details['cover_address'])],
                ])),
            ],
            [
                'title' => (string)\__('Services and handover'),
                'items' => [
                    ['title' => (string)\__('Services'), 'rows' => $this->serviceRows($details)],
                    ['title' => (string)\__('Handover to the carrier'), 'rows' => $this->pickupRows($shipment, $details)],
                ],
            ],
        ];

        $tables = [$this->packsTable($details)];
        if (!empty($details['items'])) {
            $tables[] = $this->costTable($details);
        }
        $tables[] = $this->trackingTable($shipment);

        return [
            'title' => (string)\__('Shipment details %1', $shipment->getPolkurierNumber() ?: '#' . $shipment->getId()),
            'notes' => \array_values(\array_filter([
                $fromPolkurier ? null : (string)\__('Polkurier has not been read for this order yet: shown as it was sent.'),
                $shipment->getLastError() ? (string)\__('Last error: %1', $shipment->getLastError()) : null,
            ])),
            'errors' => \array_values($errors),
            'synced' => $this->syncedLine($shipment),
            'sections' => $sections,
            'tables' => $tables,
        ];
    }

    /**
     * @param array<string, mixed> $details
     * @return array<int, array<string, string>>
     */
    private function orderRows(ShipmentInterface $shipment, array $details): array
    {
        $order = $this->links->order($shipment->getOrderId());
        $magentoShipment = $this->links->shipment($shipment->getShipmentId());

        return $this->format->rows([
            [\__('Number'), $shipment->getPolkurierNumber() ?: '—'],
            [\__('State'), $this->labels->state($shipment->getState())],
            [\__('Carrier'), empty($details['carrier_name'])
                ? $shipment->getCarrierCode()
                : $details['carrier_name'] . ' (' . $shipment->getCarrierCode() . ')'],
            [\__('Shipment type'), $this->labels->shipmentType($details['shipment_type'] ?? null)],
            [\__('Contents'), $details['description'] ?? $shipment->getDescription()],
            [\__('Placed'), $this->format->storedTime($shipment->getCreatedAt())],
            [\__('Magento order'), $order['label'] ?? '#' . $shipment->getOrderId(), $order['url'] ?? null],
            [\__('Magento shipment'), $magentoShipment['label'] ?? (string)\__('none yet'), $magentoShipment['url'] ?? null],
        ]);
    }

    /**
     * @param array<string, mixed> $details
     * @return array<int, array<string, string>>
     */
    private function deliveryRows(ShipmentInterface $shipment, array $details): array
    {
        $url = (string)($details['tracking_url'] ?? $shipment->getTrackingUrl() ?? '');
        $rows = [
            [\__('Status'), $shipment->getStatusLabel() ?: '—'],
            [\__('Status date'), $this->format->polkurierTime($shipment->getStatusDate())],
            [\__('Delivered'), $this->format->polkurierTime($shipment->getDeliveredAt())],
        ];
        foreach ($shipment->getWaybills() as $waybill) {
            $rows[] = [\__('Waybill'), $waybill, $url !== '' ? $url : null];
        }
        if ($shipment->getWaybills() === []) {
            $rows[] = [\__('Waybill'), (string)\__('not issued yet')];
        }

        return $this->format->rows($rows);
    }

    /**
     * @param array<string, mixed>|null $address
     * @return array<int, array<string, string>>
     */
    private function addressRows(?array $address): array
    {
        if ($address === null) {
            return $this->format->rows([[\__('Address'), '—']]);
        }
        $point = (string)($address['point_id'] ?? '');

        return $this->format->rows([
            [\__('Name'), \trim(\implode(', ', \array_filter([$address['company'] ?? '', $address['person'] ?? ''])))],
            [\__('Street'), (string)($address['street'] ?? '')],
            [\__('City'), $this->format->place((string)($address['postcode'] ?? ''), (string)($address['city'] ?? ''), $address['country'] ?? null)],
            [\__('Phone'), (string)($address['phone'] ?? '')],
            [\__('E-mail'), (string)($address['email'] ?? '')],
            $point === '' ? null : [\__('Pickup point'), \trim($point . (!empty($address['point_label']) ? ' — ' . $address['point_label'] : ''))],
        ]);
    }

    /**
     * @param array<string, mixed> $details
     * @return array<int, array<string, string>>
     */
    private function serviceRows(array $details): array
    {
        $cod = $details['cod'] ?? null;
        $rows = [];
        if (\is_array($cod) && (float)($cod['amount'] ?? 0) > 0) {
            $rows[] = [\__('Cash on delivery'), $this->format->money((float)$cod['amount'])];
            $rows[] = [\__('Payout time'), $this->labels->codType($cod['type'] ?? null)];
            $rows[] = [\__('Paid out to'), \trim($this->labels->codReturnType($cod['return_type'] ?? null) . (!empty($cod['bank_account']) ? ': ' . $cod['bank_account'] : ''))];
            if (!empty($cod['status'])) {
                $rows[] = [\__('COD status'), $this->labels->codStatus($cod['status'])];
            }
        } else {
            $rows[] = [\__('Cash on delivery'), (string)\__('none')];
        }
        $insurance = (float)($details['insurance'] ?? 0);
        $rows[] = [\__('Insurance'), $insurance > 0 ? $this->format->money($insurance) : (string)\__('none')];
        $services = (array)($details['services'] ?? []);
        $rows[] = [\__('Additional services'), $services === [] ? (string)\__('none') : \implode(', ', $services)];
        foreach ((array)($details['additional_fields'] ?? []) as $name => $value) {
            $rows[] = [(string)$name, (string)$value];
        }

        return $this->format->rows($rows);
    }

    /**
     * @param array<string, mixed> $details
     * @return array<int, array<string, string>>
     */
    private function pickupRows(ShipmentInterface $shipment, array $details): array
    {
        $pickup = \is_array($details['pickup'] ?? null) ? $details['pickup'] : [];
        if (!empty($pickup['multi_pickup']) || $shipment->isMultiPickup()) {
            $mode = (string)\__('Collected later, together with other shipments');
        } elseif (!empty($pickup['no_courier_order'])) {
            $mode = (string)\__('Dropped off at a point');
        } else {
            $mode = (string)\__('A courier collects them');
        }
        $window = \trim(($pickup['time_from'] ?? '') . '–' . ($pickup['time_to'] ?? ''), '–');

        return $this->format->rows([
            [\__('Handover'), $mode],
            empty($pickup['date']) ? null : [\__('Collection date'), \trim($pickup['date'] . ($window !== '' ? ', ' . $window : ''))],
            empty($pickup['pickup_number']) ? null : [\__('Pickup number'), (string)$pickup['pickup_number']],
            $shipment->isMultiPickup() ? [\__('Collective pickup booked'), $shipment->isPickupBooked() ? (string)\__('Yes') : (string)\__('No')] : null,
        ]);
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function packsTable(array $details): array
    {
        $rows = [];
        foreach ((array)($details['packs'] ?? []) as $pack) {
            $rows[] = [
                $this->labels->packType($pack['type'] ?? null),
                $this->format->size($pack['length'] ?? null, $pack['width'] ?? null, $pack['height'] ?? null),
                $this->format->weight((float)($pack['weight'] ?? 0)),
                (string)($pack['amount'] ?? 1),
                isset($pack['price_gross']) ? $this->format->money((float)$pack['price_gross']) : '—',
            ];
        }

        return [
            'title' => (string)\__('Parcels'),
            'columns' => [(string)\__('Type'), (string)\__('Size'), (string)\__('Weight'), (string)\__('Quantity'), (string)\__('Price (gross)')],
            'rows' => $rows,
            'empty' => (string)\__('No parcels.'),
        ];
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function costTable(array $details): array
    {
        $rows = [];
        foreach ((array)$details['items'] as $item) {
            $rows[] = [
                (string)($item['description'] ?? ''),
                (string)($item['amount'] ?? 1),
                $this->format->money((float)($item['value_net'] ?? 0)),
                $this->format->money((float)($item['value_gross'] ?? 0)),
            ];
        }
        $foot = [(string)\__('Total'), '', $this->format->money((float)($details['price_net'] ?? 0)), $this->format->money((float)($details['price_gross'] ?? 0))];
        $notes = [];
        if ((float)($details['rebate_gross'] ?? 0) > 0) {
            $notes[] = (string)\__('Rebate: %1', $this->format->money((float)$details['rebate_gross']));
        }
        if (isset($details['is_paid'])) {
            $notes[] = $details['is_paid']
                ? (string)\__('Paid')
                : (string)\__('Unpaid: %1', $this->format->money((float)($details['unpaid_amount'] ?? 0)));
        }
        if (!empty($details['has_invoice'])) {
            $notes[] = (string)\__('Invoiced');
        }

        return [
            'title' => (string)\__('Cost'),
            'columns' => [(string)\__('Item'), (string)\__('Quantity'), (string)\__('Net'), (string)\__('Gross')],
            'rows' => $rows,
            'foot' => $foot,
            'note' => \implode(' · ', $notes),
            'empty' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trackingTable(ShipmentInterface $shipment): array
    {
        $rows = [];
        foreach ($shipment->getTracking() as $event) {
            $rows[] = [
                $this->format->polkurierTime($event['date'] ?? null),
                (string)($event['status'] ?? ''),
                (string)($event['description'] ?? ''),
                (string)($event['location'] ?? ''),
            ];
        }

        return [
            'title' => (string)\__('Status history'),
            'columns' => [(string)\__('Date'), (string)\__('Status'), (string)\__('Description'), (string)\__('Location')],
            'rows' => $rows,
            'empty' => $shipment->getPolkurierNumber() === null
                ? (string)\__('There is no Polkurier order to follow.')
                : (string)\__('The carrier has not reported any scans yet.'),
        ];
    }

    private function syncedLine(ShipmentInterface $shipment): string
    {
        if ($shipment->getDetailsSyncedAt() === null) {
            return '';
        }

        return (string)\__('Read from Polkurier %1.', $this->format->storedTime($shipment->getDetailsSyncedAt()));
    }
}
