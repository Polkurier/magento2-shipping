<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\Draft\ItemFactory;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Shipping\Model\Shipment\DraftFactory;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;

#[CoversClass(DraftHydrator::class)]
#[CoversClass(Draft::class)]
#[AllowMockObjectsWithoutExpectations]
class DraftHydratorTest extends TestCase
{
    public function testTheScreenIsToldWhenTheParcelsAreAnEstimate(): void
    {
        $hydrator = new DraftHydrator(
            $this->createStub(DraftFactory::class),
            $this->createStub(PackFactory::class),
            $this->createStub(ItemFactory::class),
        );

        self::assertFalse($hydrator->toArray(new Draft())['packs_estimated']);
        self::assertTrue($hydrator->toArray((new Draft())->setPacksEstimated(true))['packs_estimated']);
    }
}
