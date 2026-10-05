<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\PackKinds;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;

#[CoversClass(PackKinds::class)]
class PackKindsTest extends TestCase
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
            PackKinds::ENVELOPE => 'envelope',
            PackKinds::EURO_PALLET => 'palette',
            PackKinds::EURO_PALLET_NON_STANDARD => 'palette',
        ], PackKinds::map());
        self::assertSame(ShipmentTypeEnum::Box, PackKinds::shipmentTypeOf('unknown'));
    }

    public function testAnEnvelopeGoesAsAStandardParcel(): void
    {
        self::assertSame(PackTypeEnum::Standard, PackKinds::polkurierTypeOf(PackKinds::ENVELOPE));
        self::assertSame(PackTypeEnum::HalfPalette, PackKinds::polkurierTypeOf('PPAL'));
        self::assertSame(PackTypeEnum::Standard, PackKinds::polkurierTypeOf(''));
    }

    public function testPolkurierTemplatesReadByTheirShipmentType(): void
    {
        self::assertSame(PackKinds::ENVELOPE, PackKinds::fromPolkurier('envelope', 'ST'));
        // A pallet is one by the shipment type, whatever its parcel type says.
        // Under a pallet shipment ST and NST are the EURO pallet and its non-standard variant.
        self::assertSame('EURO', PackKinds::fromPolkurier(ShipmentTypeEnum::Palette, PackTypeEnum::Standard));
        self::assertSame('EURO_NST', PackKinds::fromPolkurier('palette', 'NST'));
        self::assertSame('PAL', PackKinds::fromPolkurier('palette', 'PAL'));
        self::assertSame('PPAL', PackKinds::fromPolkurier('palette', 'PPAL'));
        // Polkurier's quarter pallet and pallets of other sizes, as made in its panel.
        self::assertSame(['VP', 'EUR2', 'PA15'], \array_map(static fn(string $type): string => PackKinds::fromPolkurier('palette', $type), ['VP', 'EUR2', 'PA15']));
        self::assertSame('NST', PackKinds::fromPolkurier('box', 'NST'));
        self::assertSame('ST', PackKinds::fromPolkurier(null, null));
    }

    public function testParcelsOfOneKindMakeOneShipmentType(): void
    {
        self::assertSame([ShipmentTypeEnum::Box], PackKinds::shipmentTypesOf(['ST', 'NST', 'DLU']));
        self::assertSame([ShipmentTypeEnum::Box, ShipmentTypeEnum::Envelope], PackKinds::shipmentTypesOf(['ST', PackKinds::ENVELOPE, 'ST']));
        self::assertSame([], PackKinds::shipmentTypesOf([]));
    }

    public function testTheEuroPalletGoesAsAPalletShipmentsStandardParcel(): void
    {
        self::assertSame(ShipmentTypeEnum::Palette, PackKinds::shipmentTypeOf('EURO'));
        self::assertSame(PackTypeEnum::Standard, PackKinds::polkurierTypeOf('EURO'));
        self::assertSame(PackTypeEnum::NonStandard, PackKinds::polkurierTypeOf('EURO_NST'));
        self::assertSame(PackTypeEnum::Palette, PackKinds::polkurierTypeOf('PAL'));
    }

    public function testAPalletOfItsOwnSizeIsTheSmallestItStandsOn(): void
    {
        self::assertSame('VP', PackKinds::palletFor(40, 60));
        self::assertSame('EURO', PackKinds::palletFor(110, 75));
        self::assertSame('EUR2', PackKinds::palletFor(90, 115));
        self::assertSame('PA15', PackKinds::palletFor(200, 140), 'Larger than every pallet: the largest.');
    }
}
