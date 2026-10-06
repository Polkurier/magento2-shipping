<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\Shipment\PackChanges;

#[CoversClass(PackChanges::class)]
class PackChangesTest extends TestCase
{
    public function testAParcelTakenAsAnotherTypeIsNamed(): void
    {
        // A 35 kg standard parcel DPD takes as non-standard, after a light one it takes as sent.
        $changes = $this->changes(
            [['type' => 'ST', 'amount' => 1, 'weight' => 2.0], ['type' => 'ST', 'amount' => 1, 'weight' => 35.0]],
            [['type' => 'ST', 'amount' => 1], ['type' => 'NST', 'amount' => 1]],
        );

        self::assertSame(['Polkurier took parcel 2 as Non-standard, not Standard, and priced it as that.'], $changes);
    }

    public function testParcelsOfSeveralAlikeAreCountedOneByOne(): void
    {
        self::assertSame([], $this->changes([['type' => 'ST', 'amount' => 2]], [['type' => 'ST', 'amount' => 1], ['type' => 'ST', 'amount' => 1]]));
        self::assertSame(
            ['Polkurier took parcel 2 as Non-standard, not Standard, and priced it as that.'],
            $this->changes([['type' => 'ST', 'amount' => 2]], [['type' => 'ST', 'amount' => 1], ['type' => 'NST', 'amount' => 1]]),
        );
    }

    public function testNothingIsToldWithoutPolkuriersCopy(): void
    {
        self::assertSame([], $this->changes([['type' => 'ST', 'amount' => 1]], []));
    }

    /**
     * @param array<int, array<string, mixed>> $sent
     * @param array<int, array<string, mixed>> $taken
     *
     * @return string[]
     */
    private function changes(array $sent, array $taken): array
    {
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getRequest')->willReturn((string)\json_encode(['courier' => 'DPD', 'packs' => $sent]));
        $shipment->method('getDetails')->willReturn($taken === [] ? [] : ['packs' => $taken]);

        return (new PackChanges(new PackType()))->describe($shipment);
    }
}
