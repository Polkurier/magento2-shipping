<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Polkurier\Webservice\Enum\Order\PackTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;

/**
 * A parcel's type says what it is: a parcel (standard, non-standard or long), an envelope or a
 * pallet (half or whole). Polkurier takes that as two fields, the order's shipment type and each
 * parcel's type, so a shipment's type follows from its parcels, which are all of one kind.
 */
final class PackKinds
{
    /**
     * An envelope, which Polkurier knows only as a shipment type: its parcels go as standard.
     */
    public const string ENVELOPE = 'ENV';

    /**
     * @return string the parcel type for the shipment type and the parcel type Polkurier has
     */
    public static function fromPolkurier(ShipmentTypeEnum|string|null $shipmentType, PackTypeEnum|string|null $packType): string
    {
        $shipmentType = $shipmentType instanceof ShipmentTypeEnum ? $shipmentType : ShipmentTypeEnum::tryFrom((string)$shipmentType);
        $packType = $packType instanceof PackTypeEnum ? $packType : PackTypeEnum::tryFrom((string)$packType);

        return match ($shipmentType) {
            ShipmentTypeEnum::Envelope => self::ENVELOPE,
            ShipmentTypeEnum::Palette => $packType === PackTypeEnum::HalfPalette ? PackTypeEnum::HalfPalette->value : PackTypeEnum::Palette->value,
            default => ($packType ?? PackTypeEnum::Standard)->value,
        };
    }

    /**
     * @return string a parcel type there is, standard for anything else
     */
    public static function normalize(?string $packType): string
    {
        return $packType === self::ENVELOPE ? self::ENVELOPE : (PackTypeEnum::tryFrom((string)$packType) ?? PackTypeEnum::Standard)->value;
    }

    public static function shipmentTypeOf(?string $packType): ShipmentTypeEnum
    {
        return match (self::normalize($packType)) {
            self::ENVELOPE => ShipmentTypeEnum::Envelope,
            PackTypeEnum::HalfPalette->value, PackTypeEnum::Palette->value => ShipmentTypeEnum::Palette,
            default => ShipmentTypeEnum::Box,
        };
    }

    /**
     * The parcel type Polkurier takes.
     */
    public static function polkurierTypeOf(?string $packType): PackTypeEnum
    {
        $packType = self::normalize($packType);

        return $packType === self::ENVELOPE ? PackTypeEnum::Standard : PackTypeEnum::from($packType);
    }

    /**
     * @param string[] $packTypes
     * @return ShipmentTypeEnum[] the shipment types of the parcels, each once: one for a shipment
     *     that can go, none without parcels
     */
    public static function shipmentTypesOf(array $packTypes): array
    {
        $types = [];
        foreach ($packTypes as $packType) {
            $type = self::shipmentTypeOf($packType);
            $types[$type->value] = $type;
        }

        return \array_values($types);
    }

    /**
     * @return array<string, string> the shipment type of each parcel type, for the screens
     */
    public static function map(): array
    {
        $map = [];
        foreach ([...\array_map(static fn(PackTypeEnum $type): string => $type->value, PackTypeEnum::cases()), self::ENVELOPE] as $packType) {
            $map[$packType] = self::shipmentTypeOf($packType)->value;
        }

        return $map;
    }
}
