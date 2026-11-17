<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Placement;

use Polkurier\Webservice\Enum\Order\PackTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;

/**
 * A parcel's type says what it is: a parcel (standard, non-standard or long), an envelope or a
 * pallet, by its size. Polkurier takes that as two fields, the order's shipment type and each
 * parcel's type, so a shipment's type follows from its parcels, which are all of one kind.
 *
 * Under a pallet shipment Polkurier reads ST and NST as the EURO pallet (120 × 80) and its
 * non-standard variant, so those two have codes of their own here, as the envelope has.
 */
final class ParcelTypeMapper
{
    /**
     * An envelope, which Polkurier knows only as a shipment type: its parcels go as standard.
     */
    public const string ENVELOPE = 'ENV';

    /**
     * The EURO pallet, 120 × 80: a pallet shipment's standard parcel.
     */
    public const string EURO_PALLET = 'EURO';

    /**
     * A non-standard EURO pallet, 120 × 80: a pallet shipment's non-standard parcel.
     */
    public const string EURO_PALLET_NON_STANDARD = 'EURO_NST';

    /**
     * The pallets by the footprint each is, in centimetres, smallest first.
     */
    public const array PALLET_SIZES = [
        PackTypeEnum::QuarterPalette->value => [60, 40],
        PackTypeEnum::HalfPalette->value => [80, 60],
        self::EURO_PALLET => [120, 80],
        PackTypeEnum::Palette120x100->value => [120, 100],
        PackTypeEnum::Palette->value => [120, 120],
        PackTypeEnum::Palette150x150->value => [150, 150],
    ];

    /**
     * @return string the parcel type for the shipment type and parcel type Polkurier reports
     */
    public static function mapFromPolkurier(
        ShipmentTypeEnum|string|null $shipmentType,
        PackTypeEnum|string|null $packType,
    ): string {
        $shipmentType = $shipmentType instanceof ShipmentTypeEnum
            ? $shipmentType
            : ShipmentTypeEnum::tryFrom((string)$shipmentType);
        $packType = $packType instanceof PackTypeEnum ? $packType : PackTypeEnum::tryFrom((string)$packType);

        return match ($shipmentType) {
            ShipmentTypeEnum::Envelope => self::ENVELOPE,
            ShipmentTypeEnum::Palette => match ($packType) {
                PackTypeEnum::NonStandard => self::EURO_PALLET_NON_STANDARD,
                PackTypeEnum::QuarterPalette, PackTypeEnum::HalfPalette, PackTypeEnum::Palette,
                PackTypeEnum::Palette120x100, PackTypeEnum::Palette150x150 => $packType->value,
                // Standard, or a type the SDK doesn't know: the EURO pallet, Polkurier's default.
                default => self::EURO_PALLET,
            },
            default => ($packType ?? PackTypeEnum::Standard)->value,
        };
    }

    /**
     * @return string a known parcel type; standard for anything else
     */
    public static function normalize(?string $packType): string
    {
        return \in_array($packType, [self::ENVELOPE, self::EURO_PALLET, self::EURO_PALLET_NON_STANDARD], true)
            ? (string)$packType
            : (PackTypeEnum::tryFrom((string)$packType) ?? PackTypeEnum::Standard)->value;
    }

    public static function mapShipmentType(?string $packType): ShipmentTypeEnum
    {
        $packType = self::normalize($packType);

        return match (true) {
            $packType === self::ENVELOPE => ShipmentTypeEnum::Envelope,
            $packType === self::EURO_PALLET_NON_STANDARD,
            isset(self::PALLET_SIZES[$packType]) => ShipmentTypeEnum::Palette,
            default => ShipmentTypeEnum::Box,
        };
    }

    /**
     * The parcel type Polkurier takes.
     */
    public static function mapPolkurierType(?string $packType): PackTypeEnum
    {
        return match ($packType = self::normalize($packType)) {
            self::ENVELOPE, self::EURO_PALLET => PackTypeEnum::Standard,
            self::EURO_PALLET_NON_STANDARD => PackTypeEnum::NonStandard,
            default => PackTypeEnum::from($packType),
        };
    }

    /**
     * The smallest pallet whose footprint takes what's this long and wide, turned either way; the
     * largest when none does.
     */
    public static function getPalletType(float $length, float $width): string
    {
        [$long, $short] = $length >= $width ? [$length, $width] : [$width, $length];
        foreach (self::PALLET_SIZES as $type => [$palletLong, $palletShort]) {
            if ($long <= $palletLong && $short <= $palletShort) {
                return $type;
            }
        }

        return \array_key_last(self::PALLET_SIZES);
    }

    /**
     * @param string[] $packTypes
     *
     * @return ShipmentTypeEnum[] the shipment types of the parcels, each once: one for a shipment
     *     that can go, none without parcels
     */
    public static function mapShipmentTypes(array $packTypes): array
    {
        $types = [];
        foreach ($packTypes as $packType) {
            $type = self::mapShipmentType($packType);
            $types[$type->value] = $type;
        }

        return \array_values($types);
    }

    /**
     * @return array<string, string> the shipment type of each parcel type, for the screens
     */
    public static function getShipmentTypeMap(): array
    {
        $map = [];
        $types = [
            ...\array_map(static fn(PackTypeEnum $type): string => $type->value, PackTypeEnum::cases()),
            self::ENVELOPE,
            self::EURO_PALLET,
            self::EURO_PALLET_NON_STANDARD,
        ];
        foreach ($types as $packType) {
            $map[$packType] = self::mapShipmentType($packType)->value;
        }

        return $map;
    }
}
