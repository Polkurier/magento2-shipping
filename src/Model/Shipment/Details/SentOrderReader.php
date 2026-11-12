<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Details;

use Magento\Framework\Serialize\Serializer\Json;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Webservice\Request\Order\CodMessage;
use Polkurier\Webservice\Request\Order\CoverAddressMessage;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Request\Order\OrderAddressMessage;
use Polkurier\Webservice\Request\Order\PickupMessage;

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
            // The cover address's flat field is flat_number; its house number is in the street.
            $flat = (string)(
                $value[OrderAddressMessage::FIELD_FLAT_NUMBER] ?? $value[CoverAddressMessage::FIELD_FLAT_NUMBER] ?? ''
            );
            $street = \trim(
                ($value[OrderAddressMessage::FIELD_STREET] ?? '')
                . ' ' . ($value[OrderAddressMessage::FIELD_HOUSE_NUMBER] ?? ''),
            ) . ($flat !== '' ? '/' . $flat : '');

            return [
                'person' => $value[OrderAddressMessage::FIELD_PERSON] ?? '',
                'company' => $value[OrderAddressMessage::FIELD_COMPANY] ?? null,
                'street' => $street,
                'postcode' => $value[OrderAddressMessage::FIELD_POSTCODE] ?? '',
                'city' => $value[OrderAddressMessage::FIELD_CITY] ?? '',
                'country' => $value[OrderAddressMessage::FIELD_COUNTRY] ?? Polkurier::HOME_COUNTRY,
                'phone' => $value[OrderAddressMessage::FIELD_PHONE] ?? '',
                'email' => $value[OrderAddressMessage::FIELD_EMAIL] ?? '',
                'point_id' => $value[OrderAddressMessage::FIELD_POINT_ID] ?? null,
                'point_label' => null,
            ];
        };
        $cod = $request[CreateOrderMessage::FIELD_CASH_ON_DELIVERY] ?? null;
        $cod = \is_array($cod) ? $cod : null;
        $pickup = $request[CreateOrderMessage::FIELD_PICKUP] ?? null;
        $pickup = \is_array($pickup) ? $pickup : null;
        $services = [];
        foreach ((array)($request[CreateOrderMessage::FIELD_COURIER_SERVICE] ?? []) as $service => $on) {
            if ($on) {
                $services[] = $this->optionLabelProvider->getServiceLabel((string)$service);
            }
        }

        return [
            'description' => $request[CreateOrderMessage::FIELD_DESCRIPTION] ?? null,
            'shipment_type' => $request[CreateOrderMessage::FIELD_SHIPMENT_TYPE] ?? null,
            'sender' => $address($request[CreateOrderMessage::FIELD_SENDER] ?? null),
            'recipient' => $address($request[CreateOrderMessage::FIELD_RECIPIENT] ?? null),
            'cover_address' => $address($request[CreateOrderMessage::FIELD_COVER_ADDRESS] ?? null),
            'cod' => $cod === null ? null : [
                'amount' => (float)($cod[CodMessage::FIELD_AMOUNT] ?? 0),
                'type' => $cod[CodMessage::FIELD_TYPE] ?? null,
                'return_type' => $cod[CodMessage::FIELD_RETURN_TYPE] ?? null,
                'bank_account' => $cod[CodMessage::FIELD_BANK_ACCOUNT] ?? null,
                'status' => null,
            ],
            'insurance' => $request[CreateOrderMessage::FIELD_INSURANCE] ?? null,
            'pickup' => $pickup === null ? null : [
                'date' => $pickup[PickupMessage::FIELD_PICKUP_DATE] ?? null,
                'time_from' => $pickup[PickupMessage::FIELD_PICKUP_TIME_FROM] ?? null,
                'time_to' => $pickup[PickupMessage::FIELD_PICKUP_TIME_TO] ?? null,
                'no_courier_order' => (bool)($pickup[PickupMessage::FIELD_NO_COURIER_ORDER] ?? false),
                'multi_pickup' => (bool)($pickup[PickupMessage::FIELD_MULTI_PICKUP] ?? false),
                'pickup_number' => null,
            ],
            'services' => $services,
            'additional_fields' => \array_map(
                'strval',
                \array_filter((array)($request[CreateOrderMessage::FIELD_ADDITIONAL_FIELDS] ?? []), 'is_scalar'),
            ),
            'packs' => \array_map(
                static fn($pack): array => (array)$pack,
                (array)($request[CreateOrderMessage::FIELD_PACKS] ?? []),
            ),
        ];
    }
}
