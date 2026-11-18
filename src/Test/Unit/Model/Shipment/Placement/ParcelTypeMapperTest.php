<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Placement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\Placement\ParcelTypeMapper;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;

#[CoversClass(ParcelTypeMapper::class)]
class ParcelTypeMapperTest extends TestCase
{
    public function testEachParcelTypeMakesItsShipmentType(): void
    {
        self::assertSame([
            'ST' => 'box',
            'NST' => 'box',
            'VP' => 'palette',
            'PPAL' => 'palette',
            'PAL' => 'palette',
            'EUR2' => 'palette',
            'PA15' => 'palette',
            'DLU' => 'box',
            ParcelTypeMapper::ENVELOPE => 'envelope',
            ParcelTypeMapper::EURO_PALLET => 'palette',
            ParcelTypeMapper::EURO_PALLET_NON_STANDARD => 'palette',
        ], ParcelTypeMapper::getShipmentTypeMap());
        self::assertSame(ShipmentTypeEnum::Box, ParcelTypeMapper::mapShipmentType('unknown'));
    }

    public function testAnEnvelopeGoesAsAStandardParcel(): void
    {
        self::assertSame(PackTypeEnum::Standard, ParcelTypeMapper::mapPolkurierType(ParcelTypeMapper::ENVELOPE));
        self::assertSame(PackTypeEnum::HalfPalette, ParcelTypeMapper::mapPolkurierType('PPAL'));
        self::assertSame(PackTypeEnum::Standard, ParcelTypeMapper::mapPolkurierType(''));
    }

    public function testPolkurierTemplatesReadByTheirShipmentType(): void
    {
        self::assertSame(ParcelTypeMapper::ENVELOPE, ParcelTypeMapper::mapFromPolkurier('envelope', 'ST'));
        // A pallet is one by the shipment type, whatever its parcel type says.
        // Under a pallet shipment ST and NST are the EURO pallet and its non-standard variant.
        self::assertSame('EURO', ParcelTypeMapper::mapFromPolkurier(ShipmentTypeEnum::Palette, PackTypeEnum::Standard));
        self::assertSame('EURO_NST', ParcelTypeMapper::mapFromPolkurier('palette', 'NST'));
        self::assertSame('PAL', ParcelTypeMapper::mapFromPolkurier('palette', 'PAL'));
        self::assertSame('PPAL', ParcelTypeMapper::mapFromPolkurier('palette', 'PPAL'));
        // Polkurier's quarter pallet and pallets of other sizes, as made in its panel.
        self::assertSame(
            ['VP', 'EUR2', 'PA15'],
            \array_map(
                static fn(string $type): string => ParcelTypeMapper::mapFromPolkurier('palette', $type),
                ['VP', 'EUR2', 'PA15'],
            ),
        );
        self::assertSame('NST', ParcelTypeMapper::mapFromPolkurier('box', 'NST'));
        self::assertSame('ST', ParcelTypeMapper::mapFromPolkurier(null, null));
    }

    public function testParcelsOfOneKindMakeOneShipmentType(): void
    {
        self::assertSame([ShipmentTypeEnum::Box], ParcelTypeMapper::mapShipmentTypes(['ST', 'NST', 'DLU']));
        self::assertSame(
            [ShipmentTypeEnum::Box, ShipmentTypeEnum::Envelope],
            ParcelTypeMapper::mapShipmentTypes(['ST', ParcelTypeMapper::ENVELOPE, 'ST']),
        );
        self::assertSame([], ParcelTypeMapper::mapShipmentTypes([]));
    }

    public function testTheEuroPalletGoesAsAPalletShipmentsStandardParcel(): void
    {
        self::assertSame(ShipmentTypeEnum::Palette, ParcelTypeMapper::mapShipmentType('EURO'));
        self::assertSame(PackTypeEnum::Standard, ParcelTypeMapper::mapPolkurierType('EURO'));
        self::assertSame(PackTypeEnum::NonStandard, ParcelTypeMapper::mapPolkurierType('EURO_NST'));
        self::assertSame(PackTypeEnum::Palette, ParcelTypeMapper::mapPolkurierType('PAL'));
    }

    public function testAPalletOfItsOwnSizeIsTheSmallestItStandsOn(): void
    {
        self::assertSame('VP', ParcelTypeMapper::getPalletType(40, 60));
        self::assertSame('EURO', ParcelTypeMapper::getPalletType(110, 75));
        self::assertSame('EUR2', ParcelTypeMapper::getPalletType(90, 115));
        self::assertSame('PA15', ParcelTypeMapper::getPalletType(200, 140), 'Larger than every pallet: the largest.');
    }
}
