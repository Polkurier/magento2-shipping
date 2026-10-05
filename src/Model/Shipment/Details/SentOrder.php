<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Details;

use Magento\Framework\Serialize\Serializer\Json;
use Polkurier\Shipping\Model\Carrier\Polkurier;

/**
 * The order as it was sent to Polkurier, in the shape of the copy read back from Polkurier, for
 * the details of shipments Polkurier hasn't been read for. An unreadable request gives nothing.
 */
readonly class SentOrder
{
    public function __construct(
        private Json $json,
        private OptionLabels $labels,
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
            $number = \trim(($value['housenumber'] ?? '') . (!empty($value['flatnumber']) ? '/' . $value['flatnumber'] : ''));

            return [
                'person' => $value['person'] ?? '',
                'company' => $value['company'] ?? null,
                'street' => \trim(($value['street'] ?? '') . ' ' . $number),
                'postcode' => $value['postcode'] ?? '',
                'city' => $value['city'] ?? '',
                'country' => $value['country'] ?? Polkurier::HOME_COUNTRY,
                'phone' => $value['phone'] ?? '',
                'email' => $value['email'] ?? '',
                'point_id' => $value['point_id'] ?? null,
                'point_label' => null,
            ];
        };
        $cod = \is_array($request['COD'] ?? null) ? $request['COD'] : null;
        $pickup = \is_array($request['pickup'] ?? null) ? $request['pickup'] : null;
        $services = [];
        foreach ((array)($request['courierservice'] ?? []) as $service => $on) {
            if ($on) {
                $services[] = $this->labels->service((string)$service);
            }
        }

        return [
            'description' => $request['description'] ?? null,
            'shipment_type' => $request['shipmenttype'] ?? null,
            'sender' => $address($request['sender'] ?? null),
            'recipient' => $address($request['recipient'] ?? null),
            'cover_address' => $address($request['cover_address'] ?? null),
            'cod' => $cod === null ? null : [
                'amount' => (float)($cod['codamount'] ?? 0),
                'type' => $cod['codtype'] ?? null,
                'return_type' => $cod['return_cod'] ?? null,
                'bank_account' => $cod['codbankaccount'] ?? null,
                'status' => null,
            ],
            'insurance' => $request['insurance'] ?? null,
            'pickup' => $pickup === null ? null : [
                'date' => $pickup['pickupdate'] ?? null,
                'time_from' => $pickup['pickuptimefrom'] ?? null,
                'time_to' => $pickup['pickuptimeto'] ?? null,
                'no_courier_order' => (bool)($pickup['nocourierorder'] ?? false),
                'multi_pickup' => (bool)($pickup['multiPickup'] ?? false),
                'pickup_number' => null,
            ],
            'services' => $services,
            'additional_fields' => \array_map('strval', \array_filter((array)($request['additional_fields'] ?? []), 'is_scalar')),
            'packs' => \array_map(static fn($pack): array => (array)$pack, (array)($request['packs'] ?? [])),
        ];
    }
}
