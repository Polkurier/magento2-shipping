<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Config\Source\PackType;

/**
 * Parcels Polkurier took as another type than they were sent as, from the order as it reports it:
 * it changes a type without saying so, e.g. a standard parcel over a carrier's weight to a
 * non-standard one, and prices it as that. Without Polkurier's copy of the order, there's nothing
 * to tell.
 */
readonly class PackChanges
{
    public function __construct(
        private PackType $packTypes,
    ) {}

    /**
     * @return string[] one sentence per parcel taken as another type
     */
    public function describe(ShipmentInterface $shipment): array
    {
        $details = $shipment->getDetails();
        if (empty($details['packs'])) {
            return [];
        }
        $request = \json_decode((string)$shipment->getRequest(), true);
        $sent = self::types(\is_array($request) ? (array)($request['packs'] ?? []) : []);
        $taken = self::types((array)$details['packs']);
        if ($sent === [] || \count($sent) !== \count($taken)) {
            return [];
        }
        $names = $this->packTypes->toArray();
        $changes = [];
        foreach ($sent as $index => $type) {
            if ($taken[$index] !== $type) {
                $changes[] = (string)\__(
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
     * The type of each parcel, the packs of several alike counted one by one.
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
}
