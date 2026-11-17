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
use Polkurier\Shipping\Api\Data\ShipmentDetailsInterface;
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
    public function buildPage(ShipmentInterface $shipment, array $errors = []): array
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
                'title' => (string)__('Order and delivery'),
                'items' => [
                    [
                        'title' => (string)__('Polkurier order'),
                        'rows' => $this->buildOrderRows($shipment, $details),
                    ],
                    [
                        'title' => (string)__('Delivery'),
                        'rows' => $this->buildDeliveryRows($shipment, $details, $asSent),
                    ],
                ],
            ],
            [
                'title' => (string)__('Sender and recipient'),
                'items' => \array_values(\array_filter([
                    [
                        'title' => (string)__('Sender'),
                        'rows' => $this->buildAddressRows(
                            $details[ShipmentDetailsInterface::FIELD_SENDER] ?? null,
                            ShipmentDetailsInterface::FIELD_SENDER,
                            $asSent,
                        ),
                    ],
                    [
                        'title' => (string)__('Recipient'),
                        'rows' => $this->buildAddressRows(
                            $details[ShipmentDetailsInterface::FIELD_RECIPIENT] ?? null,
                            ShipmentDetailsInterface::FIELD_RECIPIENT,
                            $asSent,
                        ),
                    ],
                    empty($details[ShipmentDetailsInterface::FIELD_COVER_ADDRESS]) ? null
                        : [
                            'title' => (string)__('Sender on the label'),
                            'rows' => $this->buildAddressRows(
                                $details[ShipmentDetailsInterface::FIELD_COVER_ADDRESS],
                                ShipmentDetailsInterface::FIELD_COVER_ADDRESS,
                                $asSent,
                            ),
                        ],
                ])),
            ],
            [
                'title' => (string)__('Services and handover'),
                'items' => [
                    ['title' => (string)__('Services'), 'rows' => $this->buildServiceRows($details, $asSent)],
                    [
                        'title' => (string)__('Handover to the carrier'),
                        'rows' => $this->buildPickupRows($shipment, $details, $asSent),
                    ],
                ],
            ],
        ];

        $products = $this->loadProducts($shipment);
        $tables = [
            $this->buildItemsTable($shipment, $products),
            $this->buildPacksTable($details, $shipment, $products),
        ];
        if (!empty($details[ShipmentDetailsInterface::FIELD_ITEMS])) {
            $tables[] = $this->buildCostTable($details);
        }
        $tables[] = $this->buildTrackingTable($shipment);

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
            'synced' => $this->buildSyncedLine($shipment),
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
        if (empty($details[ShipmentDetailsInterface::FIELD_PACKS])) {
            return [];
        }
        $sent = $this->sentOrderReader->read((string)$shipment->getRequest());
        $sent = self::listTypes((array)($sent[ShipmentDetailsInterface::FIELD_PACKS] ?? []));
        $taken = self::listTypes((array)$details[ShipmentDetailsInterface::FIELD_PACKS]);
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
    private function buildOrderRows(ShipmentInterface $shipment, array $details): array
    {
        $order = $this->buildOrderLink($shipment->getOrderId());
        $magentoShipment = $this->buildMagentoShipmentLink($shipment->getShipmentId());

        return $this->formatter->formatRows([
            [__('Number'), $shipment->getPolkurierNumber() ?: '—'],
            [__('Order state'), $this->optionLabelProvider->getStateLabel($shipment->getState())],
            [__('Placed'), $this->formatter->formatStoredTime($shipment->getCreatedAt())],
            // What Polkurier charges for the order, and how much of it is still due: known once
            // Polkurier's copy is read.
            !isset($details[ShipmentDetailsInterface::FIELD_PRICE_GROSS]) ? null : [
                __('Order value'),
                $this->formatter->formatMoney((float)$details[ShipmentDetailsInterface::FIELD_PRICE_GROSS]),
            ],
            !isset($details[ShipmentDetailsInterface::FIELD_UNPAID_AMOUNT]) ? null : [
                __('Amount to pay'),
                $this->formatter->formatMoney((float)$details[ShipmentDetailsInterface::FIELD_UNPAID_AMOUNT]),
            ],
            !isset($details[ShipmentDetailsInterface::FIELD_HAS_INVOICE]) ? null : [
                __('Invoiced'),
                $details[ShipmentDetailsInterface::FIELD_HAS_INVOICE] ? (string)__('Yes') : (string)__('No'),
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
    private function buildDeliveryRows(ShipmentInterface $shipment, array $details, array $asSent): array
    {
        $url = (string)($details[ShipmentDetailsInterface::FIELD_TRACKING_URL] ?? $shipment->getTrackingUrl() ?? '');
        $rows = [
            [__('Carrier'), empty($details[ShipmentDetailsInterface::FIELD_CARRIER_NAME])
                ? $this->carrierNameProvider->get($shipment->getCarrierCode())
                : (string)$details[ShipmentDetailsInterface::FIELD_CARRIER_NAME]],
            [
                __('Shipment type'),
                $this->optionLabelProvider->getShipmentTypeLabel(
                    $details[ShipmentDetailsInterface::FIELD_SHIPMENT_TYPE] ?? null,
                ),
                null,
                self::isSent($asSent, null, ShipmentDetailsInterface::FIELD_SHIPMENT_TYPE),
            ],
            [
                __('Shipment description'),
                $details[ShipmentDetailsInterface::FIELD_DESCRIPTION] ?? $shipment->getDescription(),
                null,
                self::isSent($asSent, null, ShipmentDetailsInterface::FIELD_DESCRIPTION),
            ],
            [__('Delivery status'), $shipment->getStatusLabel() ?: '—'],
            [__('Status date'), $this->formatter->formatStoredTime($shipment->getStatusDate())],
            [__('Delivered'), $this->formatter->formatStoredTime($shipment->getDeliveredAt())],
        ];
        foreach ($shipment->getWaybills() as $waybill) {
            // The carrier's tracking page, in a new tab.
            $rows[] = [__('Waybill'), $waybill, $url !== '' ? $url : null, false, true];
        }
        if ($shipment->getWaybills() === []) {
            $rows[] = [__('Waybill'), (string)__('not issued yet')];
        }

        return $this->formatter->formatRows($rows);
    }

    /**
     * @param array<string, mixed>|null $address
     * @param string $key the address's key in the details
     * @param array<string, true> $asSent
     *
     * @return array<int, array<string, string|bool>>
     */
    private function buildAddressRows(?array $address, string $key, array $asSent): array
    {
        if ($address === null) {
            return $this->formatter->formatRows([[__('Address'), '—']]);
        }
        // The sender's point is where the parcels are dropped off: shown with the handover.
        $point = $key === ShipmentDetailsInterface::FIELD_SENDER
            ? ''
            : (string)($address[ShipmentDetailsInterface::FIELD_ADDRESS_POINT_ID] ?? '');

        return $this->formatter->formatRows([
            [
                __('Name'),
                \trim(\implode(', ', \array_filter([
                    $address[ShipmentDetailsInterface::FIELD_ADDRESS_COMPANY] ?? '',
                    $address[ShipmentDetailsInterface::FIELD_ADDRESS_PERSON] ?? '',
                ]))),
                null,
                self::isSent(
                    $asSent,
                    $key,
                    ShipmentDetailsInterface::FIELD_ADDRESS_COMPANY,
                    ShipmentDetailsInterface::FIELD_ADDRESS_PERSON,
                ),
            ],
            [
                __('Street'),
                (string)($address[ShipmentDetailsInterface::FIELD_ADDRESS_STREET] ?? ''),
                null,
                self::isSent($asSent, $key, ShipmentDetailsInterface::FIELD_ADDRESS_STREET),
            ],
            [
                __('City'),
                $this->formatter->formatPlace(
                    (string)($address[ShipmentDetailsInterface::FIELD_ADDRESS_POSTCODE] ?? ''),
                    (string)($address[ShipmentDetailsInterface::FIELD_ADDRESS_CITY] ?? ''),
                    $address[ShipmentDetailsInterface::FIELD_ADDRESS_COUNTRY] ?? null,
                ),
                null,
                self::isSent(
                    $asSent,
                    $key,
                    ShipmentDetailsInterface::FIELD_ADDRESS_POSTCODE,
                    ShipmentDetailsInterface::FIELD_ADDRESS_CITY,
                    ShipmentDetailsInterface::FIELD_ADDRESS_COUNTRY,
                ),
            ],
            [
                __('Phone'),
                (string)($address[ShipmentDetailsInterface::FIELD_ADDRESS_PHONE] ?? ''),
                null,
                self::isSent($asSent, $key, ShipmentDetailsInterface::FIELD_ADDRESS_PHONE),
            ],
            [
                __('E-mail'),
                (string)($address[ShipmentDetailsInterface::FIELD_ADDRESS_EMAIL] ?? ''),
                null,
                self::isSent($asSent, $key, ShipmentDetailsInterface::FIELD_ADDRESS_EMAIL),
            ],
            $point === '' ? null : [
                __('Pickup point'),
                self::formatPoint($point, $address),
                null,
                self::isSent(
                    $asSent,
                    $key,
                    ShipmentDetailsInterface::FIELD_ADDRESS_POINT_ID,
                    ShipmentDetailsInterface::FIELD_ADDRESS_POINT_LABEL,
                ),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, true> $asSent
     *
     * @return array<int, array<string, string|bool>>
     */
    private function buildServiceRows(array $details, array $asSent): array
    {
        $cod = $details[ShipmentDetailsInterface::FIELD_COD] ?? null;
        $rows = [];
        if (\is_array($cod) && (float)($cod[ShipmentDetailsInterface::FIELD_COD_AMOUNT] ?? 0) > 0) {
            $rows[] = [
                __('Cash on delivery'),
                $this->formatter->formatMoney((float)$cod[ShipmentDetailsInterface::FIELD_COD_AMOUNT]),
                null,
                self::isSent($asSent, ShipmentDetailsInterface::FIELD_COD, ShipmentDetailsInterface::FIELD_COD_AMOUNT),
            ];
            $rows[] = [
                __('Payout time'),
                $this->optionLabelProvider->getCodTypeLabel($cod[ShipmentDetailsInterface::FIELD_COD_TYPE] ?? null),
                null,
                self::isSent($asSent, ShipmentDetailsInterface::FIELD_COD, ShipmentDetailsInterface::FIELD_COD_TYPE),
            ];
            $bankAccount = (string)($cod[ShipmentDetailsInterface::FIELD_COD_BANK_ACCOUNT] ?? '');
            $rows[] = [
                __('Paid out to'),
                \trim(
                    $this->optionLabelProvider->getCodReturnTypeLabel(
                        $cod[ShipmentDetailsInterface::FIELD_COD_RETURN_TYPE] ?? null,
                    )
                    . ($bankAccount !== '' ? ': ' . $bankAccount : ''),
                ),
                null,
                self::isSent(
                    $asSent,
                    ShipmentDetailsInterface::FIELD_COD,
                    ShipmentDetailsInterface::FIELD_COD_RETURN_TYPE,
                    ShipmentDetailsInterface::FIELD_COD_BANK_ACCOUNT,
                ),
            ];
            if (!empty($cod[ShipmentDetailsInterface::FIELD_COD_STATUS])) {
                $rows[] = [
                    __('COD status'),
                    $this->optionLabelProvider->getCodStatusLabel($cod[ShipmentDetailsInterface::FIELD_COD_STATUS]),
                ];
            }
        } else {
            $rows[] = [__('Cash on delivery'), (string)__('none')];
        }
        $insurance = (float)($details[ShipmentDetailsInterface::FIELD_INSURANCE] ?? 0);
        $rows[] = [
            __('Insurance'),
            $insurance > 0 ? $this->formatter->formatMoney($insurance) : (string)__('none'),
            null,
            self::isSent($asSent, null, ShipmentDetailsInterface::FIELD_INSURANCE),
        ];
        $services = (array)($details[ShipmentDetailsInterface::FIELD_SERVICES] ?? []);
        $rows[] = [
            __('Additional services'),
            $services === [] ? (string)__('none') : \implode(', ', $services),
            null,
            self::isSent($asSent, null, ShipmentDetailsInterface::FIELD_SERVICES),
        ];
        foreach ((array)($details[ShipmentDetailsInterface::FIELD_ADDITIONAL_FIELDS] ?? []) as $name => $value) {
            $rows[] = [
                (string)$name,
                (string)$value,
                null,
                self::isSent($asSent, ShipmentDetailsInterface::FIELD_ADDITIONAL_FIELDS, (string)$name),
            ];
        }

        return $this->formatter->formatRows($rows);
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, true> $asSent
     *
     * @return array<int, array<string, string|bool>>
     */
    private function buildPickupRows(ShipmentInterface $shipment, array $details, array $asSent): array
    {
        $pickup = $details[ShipmentDetailsInterface::FIELD_PICKUP] ?? null;
        $pickup = \is_array($pickup) ? $pickup : [];
        $dropOff = null;
        if (!empty($pickup[ShipmentDetailsInterface::FIELD_PICKUP_MULTI_PICKUP]) || $shipment->isMultiPickup()) {
            $mode = (string)__('Collected later, together with other shipments');
        } elseif (!empty($pickup[ShipmentDetailsInterface::FIELD_PICKUP_NO_COURIER_ORDER])) {
            $mode = (string)__('Dropped off at a point');
            $sender = $details[ShipmentDetailsInterface::FIELD_SENDER] ?? null;
            $sender = \is_array($sender) ? $sender : [];
            $point = (string)($sender[ShipmentDetailsInterface::FIELD_ADDRESS_POINT_ID] ?? '');
            $dropOff = [
                __('Drop-off point'),
                $point === ''
                    ? (string)__('any of the carrier\'s points')
                    : self::formatPoint($point, $sender),
                null,
                self::isSent(
                    $asSent,
                    ShipmentDetailsInterface::FIELD_SENDER,
                    ShipmentDetailsInterface::FIELD_ADDRESS_POINT_ID,
                    ShipmentDetailsInterface::FIELD_ADDRESS_POINT_LABEL,
                ),
            ];
        } else {
            $mode = (string)__('A courier collects them');
        }
        $from = (string)($pickup[ShipmentDetailsInterface::FIELD_PICKUP_TIME_FROM] ?? '');
        $window = \trim($from . '–' . ($pickup[ShipmentDetailsInterface::FIELD_PICKUP_TIME_TO] ?? ''), '–');

        return $this->formatter->formatRows([
            [
                __('Handover'),
                $mode,
                null,
                self::isSent(
                    $asSent,
                    ShipmentDetailsInterface::FIELD_PICKUP,
                    ShipmentDetailsInterface::FIELD_PICKUP_MULTI_PICKUP,
                    ShipmentDetailsInterface::FIELD_PICKUP_NO_COURIER_ORDER,
                ),
            ],
            $dropOff,
            empty($pickup[ShipmentDetailsInterface::FIELD_PICKUP_DATE]) ? null : [
                __('Collection date'),
                \trim($pickup[ShipmentDetailsInterface::FIELD_PICKUP_DATE] . ($window !== '' ? ', ' . $window : '')),
                null,
                self::isSent(
                    $asSent,
                    ShipmentDetailsInterface::FIELD_PICKUP,
                    ShipmentDetailsInterface::FIELD_PICKUP_DATE,
                    ShipmentDetailsInterface::FIELD_PICKUP_TIME_FROM,
                    ShipmentDetailsInterface::FIELD_PICKUP_TIME_TO,
                ),
            ],
            // The carrier's reference for the collection it booked; parcels dropped off aren't collected.
            $dropOff !== null || empty($pickup[ShipmentDetailsInterface::FIELD_PICKUP_NUMBER]) ? null : [
                __('Collection number'),
                (string)$pickup[ShipmentDetailsInterface::FIELD_PICKUP_NUMBER],
                null,
                self::isSent(
                    $asSent,
                    ShipmentDetailsInterface::FIELD_PICKUP,
                    ShipmentDetailsInterface::FIELD_PICKUP_NUMBER,
                ),
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
    private function buildPacksTable(array $details, ShipmentInterface $shipment, array $products): array
    {
        $rows = [];
        $packs = \array_values((array)($details[ShipmentDetailsInterface::FIELD_PACKS] ?? []));
        // What each parcel holds, as it was sent: parcel by parcel, while they still match up.
        $sent = \array_values((array)($shipment->getDraft()[ShipmentDraftInterface::FIELD_PACKS] ?? []));
        $contents = \count($sent) === \count($packs) ? $sent : [];
        foreach ($packs as $index => $pack) {
            $rows[] = [
                // Whether a parcel is an envelope depends on the shipment type alone.
                $this->optionLabelProvider->getPackTypeLabel(
                    ParcelTypeMapper::mapFromPolkurier(
                        $details[ShipmentDetailsInterface::FIELD_SHIPMENT_TYPE] ?? null,
                        $pack[ShipmentDetailsInterface::FIELD_PACK_TYPE] ?? null,
                    ),
                ),
                $this->formatter->formatSize(
                    $pack[ShipmentDetailsInterface::FIELD_PACK_LENGTH] ?? null,
                    $pack[ShipmentDetailsInterface::FIELD_PACK_WIDTH] ?? null,
                    $pack[ShipmentDetailsInterface::FIELD_PACK_HEIGHT] ?? null,
                ),
                $this->formatter->formatWeight((float)($pack[ShipmentDetailsInterface::FIELD_PACK_WEIGHT] ?? 0)),
                (string)($pack[ShipmentDetailsInterface::FIELD_PACK_AMOUNT] ?? 1),
                isset($pack[ShipmentDetailsInterface::FIELD_PACK_PRICE_GROSS])
                    ? $this->formatter->formatMoney((float)$pack[ShipmentDetailsInterface::FIELD_PACK_PRICE_GROSS])
                    : '—',
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
    private function loadProducts(ShipmentInterface $shipment): array
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
    private function buildItemsTable(ShipmentInterface $shipment, array $products): array
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
                self::formatQuantity((float)$qty),
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
                $lines[] = self::formatQuantity($qty) . ' × '
                    . ($products[$id]['name'] ?? (string)__('Order item #%1', $id));
            }
        }

        return $lines !== [] ? \implode("\n", $lines) : '—';
    }

    private static function formatQuantity(float $qty): string
    {
        return \rtrim(\rtrim(\number_format($qty, 4, '.', ''), '0'), '.');
    }

    /**
     * @param array<string, mixed> $details
     *
     * @return array<string, mixed>
     */
    private function buildCostTable(array $details): array
    {
        $rows = [];
        foreach ((array)$details[ShipmentDetailsInterface::FIELD_ITEMS] as $item) {
            $rows[] = [
                (string)($item[ShipmentDetailsInterface::FIELD_ITEM_DESCRIPTION] ?? ''),
                (string)($item[ShipmentDetailsInterface::FIELD_ITEM_AMOUNT] ?? 1),
                $this->formatter->formatMoney((float)($item[ShipmentDetailsInterface::FIELD_ITEM_VALUE_NET] ?? 0)),
                $this->formatter->formatMoney((float)($item[ShipmentDetailsInterface::FIELD_ITEM_VALUE_GROSS] ?? 0)),
            ];
        }
        $foot = [
            (string)__('Total'),
            '',
            $this->formatter->formatMoney((float)($details[ShipmentDetailsInterface::FIELD_PRICE_NET] ?? 0)),
            $this->formatter->formatMoney((float)($details[ShipmentDetailsInterface::FIELD_PRICE_GROSS] ?? 0)),
        ];
        $notes = [];
        if ((float)($details[ShipmentDetailsInterface::FIELD_REBATE_GROSS] ?? 0) > 0) {
            $notes[] = (string)__(
                'Rebate: %1',
                $this->formatter->formatMoney((float)$details[ShipmentDetailsInterface::FIELD_REBATE_GROSS]),
            );
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
    private function buildTrackingTable(ShipmentInterface $shipment): array
    {
        $rows = [];
        foreach ($shipment->getTracking() as $event) {
            $rows[] = [
                $this->formatter->formatPolkurierTime($event[ShipmentDetailsInterface::FIELD_SCAN_DATE] ?? null),
                (string)($event[ShipmentDetailsInterface::FIELD_SCAN_STATUS] ?? ''),
                (string)($event[ShipmentDetailsInterface::FIELD_SCAN_DESCRIPTION] ?? ''),
                (string)($event[ShipmentDetailsInterface::FIELD_SCAN_LOCATION] ?? ''),
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

    private function buildSyncedLine(ShipmentInterface $shipment): string
    {
        if ($shipment->getDetailsSyncedAt() === null) {
            return '';
        }

        $syncedAt = $this->formatter->formatStoredTime($shipment->getDetailsSyncedAt());

        return (string)__('Read from Polkurier %1.', $syncedAt);
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
            if ($path === ShipmentDetailsInterface::FIELD_PACKS || self::isEmpty($value)) {
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
     * A point's id, with its address when the copy of the order has it.
     *
     * @param array<string, mixed> $address
     */
    private static function formatPoint(string $pointId, array $address): string
    {
        $label = (string)($address[ShipmentDetailsInterface::FIELD_ADDRESS_POINT_LABEL] ?? '');

        return \trim($pointId . ($label !== '' ? ' — ' . $label : ''));
    }

    /**
     * Whether any of the keys, anything under them, or what holds them was taken from what was sent.
     *
     * @param array<string, true> $asSent
     * @param string|null $group the key the fields are under, or null for the order's own
     */
    private static function isSent(array $asSent, ?string $group, string ...$keys): bool
    {
        foreach (\array_keys($asSent) as $taken) {
            foreach ($keys as $key) {
                $path = $group === null ? $key : $group . '.' . $key;
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
    private static function listTypes(array $packs): array
    {
        $types = [];
        foreach ($packs as $pack) {
            if (!\is_array($pack)) {
                continue;
            }
            $type = \strtoupper(\trim((string)($pack[ShipmentDetailsInterface::FIELD_PACK_TYPE] ?? '')));
            for ($i = 0; $i < \max(1, (int)($pack[ShipmentDetailsInterface::FIELD_PACK_AMOUNT] ?? 1)); $i++) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * @return array{label: string, url: string}|null null when the order is gone
     */
    private function buildOrderLink(int $orderId): ?array
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
    private function buildMagentoShipmentLink(?int $shipmentId): ?array
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
