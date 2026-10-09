<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\ViewModel\Adminhtml;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface as MagentoShipmentRepository;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\ShipmentDraft\ItemInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraft\PackInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\Shipment\Details\Formatter;
use Polkurier\Shipping\Model\Shipment\Details\OptionLabelProvider;
use Polkurier\Shipping\Model\Shipment\Details\SentOrderReader;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Placement\ParcelTypeMapper;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;

/**
 * The shipment details page as sections of labelled rows and tables, ready to show: the local
 * record, the local copy of the Polkurier order (or, until Polkurier has been read, the order as
 * it was sent) and the carrier's scans.
 */
readonly class ShipmentDetails implements ArgumentInterface
{
    public function __construct(
        private OptionLabelProvider $optionLabelProvider,
        private Formatter $formatter,
        private SentOrderReader $sentOrderReader,
        private CarrierNameProvider $carrierNameProvider,
        private OrderLoader $orderLoader,
        private OrderAmountCalculator $orderAmountCalculator,
        private PackType $packTypes,
        private MagentoShipmentRepository $magentoShipmentRepository,
        private UrlInterface $url,
    ) {}

    /**
     * @param string[] $errors why part of it could not be read just now
     *
     * @return array<string, mixed>
     */
    public function build(ShipmentInterface $shipment, array $errors = []): array
    {
        $details = $shipment->getDetails();
        $fromPolkurier = $details !== [];
        $sent = $this->sentOrderReader->read((string)$shipment->getRequest());
        $asSent = [];
        if (!$fromPolkurier) {
            $details = $sent;
        } else {
            // Polkurier's copy leaves some of what was sent empty, depending on the carrier.
            $details = self::fillGaps($details, $sent, '', $asSent);
        }

        $sections = [
            [
                'title' => (string)__('Shipment'),
                'items' => [
                    [
                        'title' => (string)__('Polkurier order'),
                        'rows' => $this->orderRows($shipment, $details),
                    ],
                    [
                        'title' => (string)__('Delivery'),
                        'rows' => $this->deliveryRows($shipment, $details, $asSent),
                    ],
                ],
            ],
            [
                'title' => (string)__('Addresses'),
                'items' => \array_values(\array_filter([
                    [
                        'title' => (string)__('Sender'),
                        'rows' => $this->addressRows($details['sender'] ?? null, 'sender', $asSent),
                    ],
                    [
                        'title' => (string)__('Recipient'),
                        'rows' => $this->addressRows($details['recipient'] ?? null, 'recipient', $asSent),
                    ],
                    empty($details['cover_address']) ? null
                        : [
                            'title' => (string)__('Sender on the label'),
                            'rows' => $this->addressRows($details['cover_address'], 'cover_address', $asSent),
                        ],
                ])),
            ],
            [
                'title' => (string)__('Services and handover'),
                'items' => [
                    ['title' => (string)__('Services'), 'rows' => $this->serviceRows($details, $asSent)],
                    [
                        'title' => (string)__('Handover to the carrier'),
                        'rows' => $this->pickupRows($shipment, $details, $asSent),
                    ],
                ],
            ],
        ];

        $products = $this->products($shipment);
        $tables = [$this->itemsTable($shipment, $products), $this->packsTable($details, $shipment, $products)];
        if (!empty($details['items'])) {
            $tables[] = $this->costTable($details);
        }
        $tables[] = $this->trackingTable($shipment);

        return [
            'title' => (string)__('Shipment details %1', $shipment->getPolkurierNumber() ?: '#' . $shipment->getId()),
            'notes' => \array_values(\array_filter([
                $fromPolkurier
                    ? null
                    : (string)__('Polkurier has not been read for this order yet: shown as it was sent.'),
                $asSent === []
                    ? null
                    : (string)__(
                        'Values marked "as sent" are empty in Polkurier\'s copy of the order, '
                        . 'and are shown as they were sent.',
                    ),
                $shipment->getLastError() ? (string)__('Last error: %1', $shipment->getLastError()) : null,
                ...$this->describePackChanges($shipment),
            ])),
            'errors' => \array_values($errors),
            'synced' => $this->syncedLine($shipment),
            'sections' => $sections,
            'tables' => $tables,
        ];
    }

    /**
     * Parcels Polkurier took as a different type than they were sent as, according to the order as
     * Polkurier reports it. Polkurier changes a type without saying so, e.g. a standard parcel over
     * a carrier's weight limit to a non-standard one, and prices it that way. Without Polkurier's
     * copy of the order, there's nothing to compare.
     *
     * @return string[] one sentence per parcel taken as another type
     */
    public function describePackChanges(ShipmentInterface $shipment): array
    {
        $details = $shipment->getDetails();
        if (empty($details['packs'])) {
            return [];
        }
        $sent = self::types((array)($this->sentOrderReader->read((string)$shipment->getRequest())['packs'] ?? []));
        $taken = self::types((array)$details['packs']);
        if ($sent === [] || \count($sent) !== \count($taken)) {
            return [];
        }
        $names = $this->packTypes->toArray();
        $changes = [];
        foreach ($sent as $index => $type) {
            if ($taken[$index] !== $type) {
                $changes[] = (string)__(
                    'Polkurier took parcel %1 as %2, not %3, and priced it as that.',
                    $index + 1,
                    $names[$taken[$index]] ?? $taken[$index],
                    $names[$type] ?? $type,
                );
            }
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $details
     *
     * @return array<int, array<string, string|bool>>
     */
    private function orderRows(ShipmentInterface $shipment, array $details): array
    {
        $order = $this->orderLink($shipment->getOrderId());
        $magentoShipment = $this->magentoShipmentLink($shipment->getShipmentId());

        return $this->formatter->rows([
            [__('Number'), $shipment->getPolkurierNumber() ?: '—'],
            [__('State'), $this->optionLabelProvider->state($shipment->getState())],
            [__('Placed'), $this->formatter->storedTime($shipment->getCreatedAt())],
            // What Polkurier charges for the order, and how much of it is still due: known once
            // Polkurier's copy is read.
            !isset($details['price_gross']) ? null : [
                __('Order value'),
                $this->formatter->money((float)$details['price_gross']),
            ],
            !isset($details['unpaid_amount']) ? null : [
                __('Amount to pay'),
                $this->formatter->money((float)$details['unpaid_amount']),
            ],
            !isset($details['has_invoice']) ? null : [
                __('Invoiced'),
                $details['has_invoice'] ? (string)__('Yes') : (string)__('No'),
            ],
            [__('Magento order'), $order['label'] ?? '#' . $shipment->getOrderId(), $order['url'] ?? null],
            [
                __('Magento shipment'),
                $magentoShipment['label'] ?? (string)__('none yet'),
                $magentoShipment['url'] ?? null,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, true> $asSent
     *
     * @return array<int, array<string, string|bool>>
     */
    private function deliveryRows(ShipmentInterface $shipment, array $details, array $asSent): array
    {
        $url = (string)($details['tracking_url'] ?? $shipment->getTrackingUrl() ?? '');
        $rows = [
            [__('Carrier'), empty($details['carrier_name'])
                ? $this->carrierNameProvider->get($shipment->getCarrierCode())
                : (string)$details['carrier_name']],
            [
                __('Shipment type'),
                $this->optionLabelProvider->shipmentType($details['shipment_type'] ?? null),
                null,
                self::isSent($asSent, 'shipment_type'),
            ],
            [
                __('Shipment description'),
                $details['description'] ?? $shipment->getDescription(),
                null,
                self::isSent($asSent, 'description'),
            ],
            [__('Status'), $shipment->getStatusLabel() ?: '—'],
            [__('Status date'), $this->formatter->storedTime($shipment->getStatusDate())],
            [__('Delivered'), $this->formatter->storedTime($shipment->getDeliveredAt())],
        ];
        foreach ($shipment->getWaybills() as $waybill) {
            $rows[] = [__('Waybill'), $waybill, $url !== '' ? $url : null];
        }
        if ($shipment->getWaybills() === []) {
            $rows[] = [__('Waybill'), (string)__('not issued yet')];
        }

        return $this->formatter->rows($rows);
    }

    /**
     * @param array<string, mixed>|null $address
     * @param string $key the address's key in the details
     * @param array<string, true> $asSent
     *
     * @return array<int, array<string, string|bool>>
     */
    private function addressRows(?array $address, string $key, array $asSent): array
    {
        if ($address === null) {
            return $this->formatter->rows([[__('Address'), '—']]);
        }
        // The sender's point is where the parcels are dropped off: shown with the handover.
        $point = $key === 'sender' ? '' : (string)($address['point_id'] ?? '');

        return $this->formatter->rows([
            [
                __('Name'),
                \trim(\implode(', ', \array_filter([$address['company'] ?? '', $address['person'] ?? '']))),
                null,
                self::isSent($asSent, $key . '.company', $key . '.person'),
            ],
            [__('Street'), (string)($address['street'] ?? ''), null, self::isSent($asSent, $key . '.street')],
            [
                __('City'),
                $this->formatter->place(
                    (string)($address['postcode'] ?? ''),
                    (string)($address['city'] ?? ''),
                    $address['country'] ?? null,
                ),
                null,
                self::isSent($asSent, $key . '.postcode', $key . '.city', $key . '.country'),
            ],
            [__('Phone'), (string)($address['phone'] ?? ''), null, self::isSent($asSent, $key . '.phone')],
            [__('E-mail'), (string)($address['email'] ?? ''), null, self::isSent($asSent, $key . '.email')],
            $point === '' ? null : [
                __('Pickup point'),
                \trim($point . (!empty($address['point_label']) ? ' — ' . $address['point_label'] : '')),
                null,
                self::isSent($asSent, $key . '.point_id', $key . '.point_label'),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, true> $asSent
     *
     * @return array<int, array<string, string|bool>>
     */
    private function serviceRows(array $details, array $asSent): array
    {
        $cod = $details['cod'] ?? null;
        $rows = [];
        if (\is_array($cod) && (float)($cod['amount'] ?? 0) > 0) {
            $rows[] = [
                __('Cash on delivery'),
                $this->formatter->money((float)$cod['amount']),
                null,
                self::isSent($asSent, 'cod.amount'),
            ];
            $rows[] = [
                __('Payout time'),
                $this->optionLabelProvider->codType($cod['type'] ?? null),
                null,
                self::isSent($asSent, 'cod.type'),
            ];
            $rows[] = [
                __('Paid out to'),
                \trim(
                    $this->optionLabelProvider->codReturnType($cod['return_type'] ?? null)
                    . (!empty($cod['bank_account']) ? ': ' . $cod['bank_account'] : ''),
                ),
                null,
                self::isSent($asSent, 'cod.return_type', 'cod.bank_account'),
            ];
            if (!empty($cod['status'])) {
                $rows[] = [__('COD status'), $this->optionLabelProvider->codStatus($cod['status'])];
            }
        } else {
            $rows[] = [__('Cash on delivery'), (string)__('none')];
        }
        $insurance = (float)($details['insurance'] ?? 0);
        $rows[] = [
            __('Insurance'),
            $insurance > 0 ? $this->formatter->money($insurance) : (string)__('none'),
            null,
            self::isSent($asSent, 'insurance'),
        ];
        $services = (array)($details['services'] ?? []);
        $rows[] = [
            __('Additional services'),
            $services === [] ? (string)__('none') : \implode(', ', $services),
            null,
            self::isSent($asSent, 'services'),
        ];
        foreach ((array)($details['additional_fields'] ?? []) as $name => $value) {
            $rows[] = [(string)$name, (string)$value, null, self::isSent($asSent, 'additional_fields.' . $name)];
        }

        return $this->formatter->rows($rows);
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, true> $asSent
     *
     * @return array<int, array<string, string|bool>>
     */
    private function pickupRows(ShipmentInterface $shipment, array $details, array $asSent): array
    {
        $pickup = \is_array($details['pickup'] ?? null) ? $details['pickup'] : [];
        $dropOff = null;
        if (!empty($pickup['multi_pickup']) || $shipment->isMultiPickup()) {
            $mode = (string)__('Collected later, together with other shipments');
        } elseif (!empty($pickup['no_courier_order'])) {
            $mode = (string)__('Dropped off at a point');
            $sender = \is_array($details['sender'] ?? null) ? $details['sender'] : [];
            $point = (string)($sender['point_id'] ?? '');
            $dropOff = [
                __('Drop-off point'),
                $point === ''
                    ? (string)__('any of the carrier\'s points')
                    : \trim($point . (!empty($sender['point_label']) ? ' — ' . $sender['point_label'] : '')),
                null,
                self::isSent($asSent, 'sender.point_id', 'sender.point_label'),
            ];
        } else {
            $mode = (string)__('A courier collects them');
        }
        $window = \trim(($pickup['time_from'] ?? '') . '–' . ($pickup['time_to'] ?? ''), '–');

        return $this->formatter->rows([
            [__('Handover'), $mode, null, self::isSent($asSent, 'pickup.multi_pickup', 'pickup.no_courier_order')],
            $dropOff,
            empty($pickup['date']) ? null : [
                __('Collection date'),
                \trim($pickup['date'] . ($window !== '' ? ', ' . $window : '')),
                null,
                self::isSent($asSent, 'pickup.date', 'pickup.time_from', 'pickup.time_to'),
            ],
            // The carrier's reference for the collection it booked; parcels dropped off aren't collected.
            $dropOff !== null || empty($pickup['pickup_number']) ? null : [
                __('Collection number'),
                (string)$pickup['pickup_number'],
                null,
                self::isSent($asSent, 'pickup.pickup_number'),
            ],
            $shipment->isMultiPickup() ? [
                __('Collective pickup booked'),
                $shipment->isPickupBooked() ? (string)__('Yes') : (string)__('No'),
            ] : null,
        ]);
    }

    /**
     * @param array<string, mixed> $details
     * @param array<int, array<string, mixed>> $products
     *
     * @return array<string, mixed>
     */
    private function packsTable(array $details, ShipmentInterface $shipment, array $products): array
    {
        $rows = [];
        $packs = \array_values((array)($details['packs'] ?? []));
        // What each parcel holds, as it was sent: parcel by parcel, while they still match up.
        $sent = \array_values((array)($shipment->getDraft()[ShipmentDraftInterface::FIELD_PACKS] ?? []));
        $contents = \count($sent) === \count($packs) ? $sent : [];
        foreach ($packs as $index => $pack) {
            $rows[] = [
                // Whether a parcel is an envelope depends on the shipment type alone.
                $this->optionLabelProvider->packType(
                    ParcelTypeMapper::fromPolkurier($details['shipment_type'] ?? null, $pack['type'] ?? null),
                ),
                $this->formatter->size($pack['length'] ?? null, $pack['width'] ?? null, $pack['height'] ?? null),
                $this->formatter->weight((float)($pack['weight'] ?? 0)),
                (string)($pack['amount'] ?? 1),
                isset($pack['price_gross']) ? $this->formatter->money((float)$pack['price_gross']) : '—',
                $this->describeContents((array)($contents[$index][PackInterface::FIELD_ITEMS] ?? []), $products),
            ];
        }

        return [
            'title' => (string)__('Parcels'),
            'columns' => [
                (string)__('Type'),
                (string)__('Size'),
                (string)__('Weight'),
                (string)__('Quantity'),
                (string)__('Price (gross)'),
                (string)__('Contents'),
            ],
            'rows' => $rows,
            'empty' => (string)__('No parcels.'),
        ];
    }

    /**
     * The order's items, by order item id, for naming what was shipped; none when the order can't
     * be read.
     *
     * @return array<int, array<string, mixed>>
     */
    private function products(ShipmentInterface $shipment): array
    {
        $order = $this->orderLoader->find($shipment->getOrderId());

        return $order !== null ? $this->orderAmountCalculator->getItems($order) : [];
    }

    /**
     * What the Polkurier order shipped of the order, as it was sent.
     *
     * @param array<int, array<string, mixed>> $products
     *
     * @return array<string, mixed>
     */
    private function itemsTable(ShipmentInterface $shipment, array $products): array
    {
        $qtys = [];
        foreach ((array)($shipment->getDraft()[ShipmentDraftInterface::FIELD_ITEMS] ?? []) as $row) {
            $id = (int)($row[ItemInterface::FIELD_ORDER_ITEM_ID] ?? 0);
            if ($id && (float)($row[ItemInterface::FIELD_QTY] ?? 0) > 0) {
                $qtys[$id] = ($qtys[$id] ?? 0.0) + (float)$row[ItemInterface::FIELD_QTY];
            }
        }
        if ($qtys === []) {
            $qtys = $shipment->getShipItems();
        }
        $rows = [];
        foreach ($qtys as $id => $qty) {
            $rows[] = [
                (string)($products[$id]['name'] ?? __('Order item #%1', $id)),
                (string)($products[$id]['sku'] ?? '—'),
                self::quantity((float)$qty),
            ];
        }

        return [
            'title' => (string)__('Items shipped'),
            'columns' => [(string)__('Product'), (string)__('SKU'), (string)__('Quantity')],
            'rows' => $rows,
            'empty' => (string)__('Which items it shipped isn\'t known.'),
        ];
    }

    /**
     * One item per line: "2 × Mug".
     *
     * @param array<int, mixed> $rows the parcel's items: order_item_id and qty
     * @param array<int, array<string, mixed>> $products
     */
    private function describeContents(array $rows, array $products): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $id = \is_array($row) ? (int)($row[ItemInterface::FIELD_ORDER_ITEM_ID] ?? 0) : 0;
            $qty = \is_array($row) ? (float)($row[ItemInterface::FIELD_QTY] ?? 0) : 0.0;
            if ($id && $qty > 0) {
                $lines[] = self::quantity($qty) . ' × '
                    . ($products[$id]['name'] ?? (string)__('Order item #%1', $id));
            }
        }

        return $lines !== [] ? \implode("\n", $lines) : '—';
    }

    private static function quantity(float $qty): string
    {
        return \rtrim(\rtrim(\number_format($qty, 4, '.', ''), '0'), '.');
    }

    /**
     * @param array<string, mixed> $details
     *
     * @return array<string, mixed>
     */
    private function costTable(array $details): array
    {
        $rows = [];
        foreach ((array)$details['items'] as $item) {
            $rows[] = [
                (string)($item['description'] ?? ''),
                (string)($item['amount'] ?? 1),
                $this->formatter->money((float)($item['value_net'] ?? 0)),
                $this->formatter->money((float)($item['value_gross'] ?? 0)),
            ];
        }
        $foot = [
            (string)__('Total'),
            '',
            $this->formatter->money((float)($details['price_net'] ?? 0)),
            $this->formatter->money((float)($details['price_gross'] ?? 0)),
        ];
        $notes = [];
        if ((float)($details['rebate_gross'] ?? 0) > 0) {
            $notes[] = (string)__('Rebate: %1', $this->formatter->money((float)$details['rebate_gross']));
        }

        return [
            'title' => (string)__('Cost'),
            'columns' => [(string)__('Item'), (string)__('Quantity'), (string)__('Net'), (string)__('Gross')],
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
                $this->formatter->polkurierTime($event['date'] ?? null),
                (string)($event['status'] ?? ''),
                (string)($event['description'] ?? ''),
                (string)($event['location'] ?? ''),
            ];
        }

        return [
            'title' => (string)__('Carrier tracking'),
            'columns' => [
                (string)__('Date'),
                (string)__('Status'),
                (string)__('Description'),
                (string)__('Location'),
            ],
            'rows' => $rows,
            'empty' => $shipment->getPolkurierNumber() === null
                ? (string)__('There is no Polkurier order to follow.')
                : (string)__('No scans from the carrier yet.'),
        ];
    }

    private function syncedLine(ShipmentInterface $shipment): string
    {
        if ($shipment->getDetailsSyncedAt() === null) {
            return '';
        }

        return (string)__('Read from Polkurier %1.', $this->formatter->storedTime($shipment->getDetailsSyncedAt()));
    }

    /**
     * Polkurier's copy of the order, with what it leaves empty (null, "" or no entries) taken from the
     * order as it was sent. Values Polkurier holds always stay, even where they differ from what was
     * sent. Parcels are compared separately ({@see self::describePackChanges()}).
     *
     * @param array<string, mixed> $copy
     * @param array<string, mixed> $sent
     * @param array<string, true> $asSent the paths taken from what was sent, e.g. "recipient.email"
     *
     * @param-out array<string, true> $asSent
     * @return array<string, mixed>
     */
    private static function fillGaps(array $copy, array $sent, string $prefix, array &$asSent): array
    {
        foreach ($sent as $key => $value) {
            $path = $prefix . $key;
            if ($path === 'packs' || self::isEmpty($value)) {
                continue;
            }
            $own = $copy[$key] ?? null;
            if (self::isEmpty($own)) {
                $copy[$key] = $value;
                $asSent[$path] = true;
            } elseif (\is_array($own) && \is_array($value) && !\array_is_list($own) && !\array_is_list($value)) {
                $copy[$key] = self::fillGaps($own, $value, $path . '.', $asSent);
            }
        }

        return $copy;
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * Whether any of the paths, anything under them, or what holds them was taken from what was sent.
     *
     * @param array<string, true> $asSent
     */
    private static function isSent(array $asSent, string ...$paths): bool
    {
        foreach (\array_keys($asSent) as $taken) {
            foreach ($paths as $path) {
                if ($taken === $path
                    || \str_starts_with($taken, $path . '.')
                    || \str_starts_with($path, $taken . '.')
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The type of each parcel, with a pack of several identical parcels counted one by one.
     *
     * @param array<int|string, mixed> $packs as sent or as Polkurier reports them
     *
     * @return string[]
     */
    private static function types(array $packs): array
    {
        $types = [];
        foreach ($packs as $pack) {
            if (!\is_array($pack)) {
                continue;
            }
            $type = \strtoupper(\trim((string)($pack['type'] ?? '')));
            for ($i = 0; $i < \max(1, (int)($pack['amount'] ?? 1)); $i++) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * @return array{label: string, url: string}|null null when the order is gone
     */
    private function orderLink(int $orderId): ?array
    {
        $order = $this->orderLoader->find($orderId);

        return $order === null ? null : [
            'label' => '#' . $order->getIncrementId(),
            'url' => $this->url->getUrl('sales/order/view', ['order_id' => $orderId]),
        ];
    }

    /**
     * @return array{label: string, url: string}|null null when there is none (yet)
     */
    private function magentoShipmentLink(?int $shipmentId): ?array
    {
        if ($shipmentId === null) {
            return null;
        }
        try {
            $shipment = $this->magentoShipmentRepository->get($shipmentId);
        } catch (NoSuchEntityException) {
            return null;
        }

        return [
            'label' => '#' . $shipment->getIncrementId(),
            'url' => $this->url->getUrl('sales/shipment/view', ['shipment_id' => $shipmentId]),
        ];
    }
}
