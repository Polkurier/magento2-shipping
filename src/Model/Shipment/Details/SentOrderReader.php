<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Details;

use Magento\Framework\Serialize\Serializer\Json;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Webservice\Entity\Order;
use Polkurier\Webservice\Entity\Order\Address;
use Polkurier\Webservice\Entity\Order\Cod;
use Polkurier\Webservice\Entity\Order\Pickup;

/**
 * The order as it was sent to Polkurier, in the shape of the copy read back from Polkurier. Used
 * for the details of shipments whose order hasn't been read from Polkurier. An unreadable request
 * gives nothing.
 */
readonly class SentOrderReader
{
    public function __construct(
        private Json $json,
        private OptionLabelProvider $optionLabelProvider,
    ) {}

    /**
     * @param string $json the request the order was placed with
     *
     * @return array<string, mixed>
     */
    public function read(string $json): array
    {
        try {
            $request = $json === '' ? null : $this->json->unserialize($json);
        } catch (\InvalidArgumentException) {
            $request = null;
        }
        if (!\is_array($request)) {
            return [];
        }
        $address = static function (mixed $value): ?array {
            if (!\is_array($value)) {
                return null;
            }
            $flat = (string)($value[Address::FIELD_FLAT_NUMBER] ?? $value[Address::FIELD_COVER_FLAT_NUMBER] ?? '');
            $street = \trim(
                ($value[Address::FIELD_STREET] ?? '')
                . ' ' . ($value[Address::FIELD_HOUSE_NUMBER] ?? ''),
            ) . ($flat !== '' ? '/' . $flat : '');

            return [
                'person' => $value[Address::FIELD_PERSON] ?? '',
                'company' => $value[Address::FIELD_COMPANY] ?? null,
                'street' => $street,
                'postcode' => $value[Address::FIELD_POSTCODE] ?? '',
                'city' => $value[Address::FIELD_CITY] ?? '',
                'country' => $value[Address::FIELD_COUNTRY] ?? Polkurier::HOME_COUNTRY,
                'phone' => $value[Address::FIELD_PHONE] ?? '',
                'email' => $value[Address::FIELD_EMAIL] ?? '',
                'point_id' => $value[Address::FIELD_POINT_ID] ?? null,
                'point_label' => null,
            ];
        };
        $cod = $request[Order::FIELD_CASH_ON_DELIVERY] ?? null;
        $cod = \is_array($cod) ? $cod : null;
        $pickup = $request[Order::FIELD_PICKUP] ?? null;
        $pickup = \is_array($pickup) ? $pickup : null;
        $services = [];
        foreach ((array)($request[Order::FIELD_COURIER_SERVICE] ?? []) as $service => $on) {
            if ($on) {
                $services[] = $this->optionLabelProvider->getServiceLabel((string)$service);
            }
        }

        return [
            'description' => $request[Order::FIELD_DESCRIPTION] ?? null,
            'shipment_type' => $request[Order::FIELD_SHIPMENT_TYPE] ?? null,
            'sender' => $address($request[Order::FIELD_SENDER] ?? null),
            'recipient' => $address($request[Order::FIELD_RECIPIENT] ?? null),
            'cover_address' => $address($request[Order::FIELD_COVER_ADDRESS] ?? null),
            'cod' => $cod === null ? null : [
                'amount' => (float)($cod[Cod::FIELD_AMOUNT] ?? 0),
                'type' => $cod[Cod::FIELD_TYPE] ?? null,
                'return_type' => $cod[Cod::FIELD_RETURN_TYPE] ?? null,
                'bank_account' => $cod[Cod::FIELD_BANK_ACCOUNT] ?? null,
                'status' => null,
            ],
            'insurance' => $request[Order::FIELD_INSURANCE] ?? null,
            'pickup' => $pickup === null ? null : [
                'date' => $pickup[Pickup::FIELD_PICKUP_DATE] ?? null,
                'time_from' => $pickup[Pickup::FIELD_PICKUP_TIME_FROM] ?? null,
                'time_to' => $pickup[Pickup::FIELD_PICKUP_TIME_TO] ?? null,
                'no_courier_order' => (bool)($pickup[Pickup::FIELD_NO_COURIER_ORDER] ?? false),
                'multi_pickup' => (bool)($pickup[Pickup::FIELD_MULTI_PICKUP] ?? false),
                'pickup_number' => null,
            ],
            'services' => $services,
            'additional_fields' => \array_map(
                'strval',
                \array_filter((array)($request[Order::FIELD_ADDITIONAL_FIELDS] ?? []), 'is_scalar'),
            ),
            'packs' => \array_map(
                static fn($pack): array => (array)$pack,
                (array)($request[Order::FIELD_PACKS] ?? []),
            ),
        ];
    }
}
