<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Details;

use Magento\Framework\Serialize\Serializer\Json;
use Polkurier\Shipping\Api\Data\ShipmentDetailsInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Webservice\Entity\Order;
use Polkurier\Webservice\Entity\Order\Address;
use Polkurier\Webservice\Entity\Order\Cod;
use Polkurier\Webservice\Entity\Order\PackItem;
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
                ShipmentDetailsInterface::FIELD_ADDRESS_PERSON => $value[Address::FIELD_PERSON] ?? '',
                ShipmentDetailsInterface::FIELD_ADDRESS_COMPANY => $value[Address::FIELD_COMPANY] ?? null,
                ShipmentDetailsInterface::FIELD_ADDRESS_STREET => $street,
                ShipmentDetailsInterface::FIELD_ADDRESS_POSTCODE => $value[Address::FIELD_POSTCODE] ?? '',
                ShipmentDetailsInterface::FIELD_ADDRESS_CITY => $value[Address::FIELD_CITY] ?? '',
                ShipmentDetailsInterface::FIELD_ADDRESS_COUNTRY => $value[Address::FIELD_COUNTRY]
                    ?? Polkurier::HOME_COUNTRY,
                ShipmentDetailsInterface::FIELD_ADDRESS_PHONE => $value[Address::FIELD_PHONE] ?? '',
                ShipmentDetailsInterface::FIELD_ADDRESS_EMAIL => $value[Address::FIELD_EMAIL] ?? '',
                ShipmentDetailsInterface::FIELD_ADDRESS_POINT_ID => $value[Address::FIELD_POINT_ID] ?? null,
                ShipmentDetailsInterface::FIELD_ADDRESS_POINT_LABEL => null,
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
            ShipmentDetailsInterface::FIELD_DESCRIPTION => $request[Order::FIELD_DESCRIPTION] ?? null,
            ShipmentDetailsInterface::FIELD_SHIPMENT_TYPE => $request[Order::FIELD_SHIPMENT_TYPE] ?? null,
            ShipmentDetailsInterface::FIELD_SENDER => $address($request[Order::FIELD_SENDER] ?? null),
            ShipmentDetailsInterface::FIELD_RECIPIENT => $address($request[Order::FIELD_RECIPIENT] ?? null),
            ShipmentDetailsInterface::FIELD_COVER_ADDRESS => $address($request[Order::FIELD_COVER_ADDRESS] ?? null),
            ShipmentDetailsInterface::FIELD_COD => $cod === null ? null : [
                ShipmentDetailsInterface::FIELD_COD_AMOUNT => (float)($cod[Cod::FIELD_AMOUNT] ?? 0),
                ShipmentDetailsInterface::FIELD_COD_TYPE => $cod[Cod::FIELD_TYPE] ?? null,
                ShipmentDetailsInterface::FIELD_COD_RETURN_TYPE => $cod[Cod::FIELD_RETURN_TYPE] ?? null,
                ShipmentDetailsInterface::FIELD_COD_BANK_ACCOUNT => $cod[Cod::FIELD_BANK_ACCOUNT] ?? null,
                ShipmentDetailsInterface::FIELD_COD_STATUS => null,
            ],
            ShipmentDetailsInterface::FIELD_INSURANCE => $request[Order::FIELD_INSURANCE] ?? null,
            ShipmentDetailsInterface::FIELD_PICKUP => $pickup === null ? null : [
                ShipmentDetailsInterface::FIELD_PICKUP_DATE => $pickup[Pickup::FIELD_PICKUP_DATE] ?? null,
                ShipmentDetailsInterface::FIELD_PICKUP_TIME_FROM => $pickup[Pickup::FIELD_PICKUP_TIME_FROM] ?? null,
                ShipmentDetailsInterface::FIELD_PICKUP_TIME_TO => $pickup[Pickup::FIELD_PICKUP_TIME_TO] ?? null,
                ShipmentDetailsInterface::FIELD_PICKUP_NO_COURIER_ORDER => (bool)(
                    $pickup[Pickup::FIELD_NO_COURIER_ORDER] ?? false
                ),
                ShipmentDetailsInterface::FIELD_PICKUP_MULTI_PICKUP => (bool)(
                    $pickup[Pickup::FIELD_MULTI_PICKUP] ?? false
                ),
                ShipmentDetailsInterface::FIELD_PICKUP_NUMBER => null,
            ],
            ShipmentDetailsInterface::FIELD_SERVICES => $services,
            ShipmentDetailsInterface::FIELD_ADDITIONAL_FIELDS => \array_map(
                'strval',
                \array_filter((array)($request[Order::FIELD_ADDITIONAL_FIELDS] ?? []), 'is_scalar'),
            ),
            ShipmentDetailsInterface::FIELD_PACKS => \array_values(\array_map(
                static fn(mixed $pack): array => [
                    ShipmentDetailsInterface::FIELD_PACK_TYPE => $pack[PackItem::FIELD_TYPE] ?? null,
                    ShipmentDetailsInterface::FIELD_PACK_LENGTH => $pack[PackItem::FIELD_LENGTH] ?? null,
                    ShipmentDetailsInterface::FIELD_PACK_WIDTH => $pack[PackItem::FIELD_WIDTH] ?? null,
                    ShipmentDetailsInterface::FIELD_PACK_HEIGHT => $pack[PackItem::FIELD_HEIGHT] ?? null,
                    ShipmentDetailsInterface::FIELD_PACK_WEIGHT => $pack[PackItem::FIELD_WEIGHT] ?? null,
                    ShipmentDetailsInterface::FIELD_PACK_AMOUNT => $pack[PackItem::FIELD_AMOUNT] ?? 1,
                ],
                \array_filter((array)($request[Order::FIELD_PACKS] ?? []), 'is_array'),
            )),
        ];
    }
}
