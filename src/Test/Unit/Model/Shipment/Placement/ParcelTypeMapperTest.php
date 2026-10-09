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
        ], ParcelTypeMapper::map());
        self::assertSame(ShipmentTypeEnum::Box, ParcelTypeMapper::shipmentTypeOf('unknown'));
    }

    public function testAnEnvelopeGoesAsAStandardParcel(): void
    {
        self::assertSame(PackTypeEnum::Standard, ParcelTypeMapper::polkurierTypeOf(ParcelTypeMapper::ENVELOPE));
        self::assertSame(PackTypeEnum::HalfPalette, ParcelTypeMapper::polkurierTypeOf('PPAL'));
        self::assertSame(PackTypeEnum::Standard, ParcelTypeMapper::polkurierTypeOf(''));
    }

    public function testPolkurierTemplatesReadByTheirShipmentType(): void
    {
        self::assertSame(ParcelTypeMapper::ENVELOPE, ParcelTypeMapper::fromPolkurier('envelope', 'ST'));
        // A pallet is one by the shipment type, whatever its parcel type says.
        // Under a pallet shipment ST and NST are the EURO pallet and its non-standard variant.
        self::assertSame('EURO', ParcelTypeMapper::fromPolkurier(ShipmentTypeEnum::Palette, PackTypeEnum::Standard));
        self::assertSame('EURO_NST', ParcelTypeMapper::fromPolkurier('palette', 'NST'));
        self::assertSame('PAL', ParcelTypeMapper::fromPolkurier('palette', 'PAL'));
        self::assertSame('PPAL', ParcelTypeMapper::fromPolkurier('palette', 'PPAL'));
        // Polkurier's quarter pallet and pallets of other sizes, as made in its panel.
        self::assertSame(
            ['VP', 'EUR2', 'PA15'],
            \array_map(
                static fn(string $type): string => ParcelTypeMapper::fromPolkurier('palette', $type),
                ['VP', 'EUR2', 'PA15'],
            ),
        );
        self::assertSame('NST', ParcelTypeMapper::fromPolkurier('box', 'NST'));
        self::assertSame('ST', ParcelTypeMapper::fromPolkurier(null, null));
    }

    public function testParcelsOfOneKindMakeOneShipmentType(): void
    {
        self::assertSame([ShipmentTypeEnum::Box], ParcelTypeMapper::shipmentTypesOf(['ST', 'NST', 'DLU']));
        self::assertSame(
            [ShipmentTypeEnum::Box, ShipmentTypeEnum::Envelope],
            ParcelTypeMapper::shipmentTypesOf(['ST', ParcelTypeMapper::ENVELOPE, 'ST']),
        );
        self::assertSame([], ParcelTypeMapper::shipmentTypesOf([]));
    }

    public function testTheEuroPalletGoesAsAPalletShipmentsStandardParcel(): void
    {
        self::assertSame(ShipmentTypeEnum::Palette, ParcelTypeMapper::shipmentTypeOf('EURO'));
        self::assertSame(PackTypeEnum::Standard, ParcelTypeMapper::polkurierTypeOf('EURO'));
        self::assertSame(PackTypeEnum::NonStandard, ParcelTypeMapper::polkurierTypeOf('EURO_NST'));
        self::assertSame(PackTypeEnum::Palette, ParcelTypeMapper::polkurierTypeOf('PAL'));
    }

    public function testAPalletOfItsOwnSizeIsTheSmallestItStandsOn(): void
    {
        self::assertSame('VP', ParcelTypeMapper::palletFor(40, 60));
        self::assertSame('EURO', ParcelTypeMapper::palletFor(110, 75));
        self::assertSame('EUR2', ParcelTypeMapper::palletFor(90, 115));
        self::assertSame('PA15', ParcelTypeMapper::palletFor(200, 140), 'Larger than every pallet: the largest.');
    }
}
