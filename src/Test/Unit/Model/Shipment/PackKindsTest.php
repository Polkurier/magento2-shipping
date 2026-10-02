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
            'PPAL' => 'palette',
            'PAL' => 'palette',
            'DLU' => 'box',
            PackKinds::ENVELOPE => 'envelope',
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
        self::assertSame('PAL', PackKinds::fromPolkurier(ShipmentTypeEnum::Palette, PackTypeEnum::Standard));
        self::assertSame('PPAL', PackKinds::fromPolkurier('palette', 'PPAL'));
        self::assertSame('NST', PackKinds::fromPolkurier('box', 'NST'));
        self::assertSame('ST', PackKinds::fromPolkurier(null, null));
    }

    public function testParcelsOfOneKindMakeOneShipmentType(): void
    {
        self::assertSame([ShipmentTypeEnum::Box], PackKinds::shipmentTypesOf(['ST', 'NST', 'DLU']));
        self::assertSame([ShipmentTypeEnum::Box, ShipmentTypeEnum::Envelope], PackKinds::shipmentTypesOf(['ST', PackKinds::ENVELOPE, 'ST']));
        self::assertSame([], PackKinds::shipmentTypesOf([]));
    }
}
