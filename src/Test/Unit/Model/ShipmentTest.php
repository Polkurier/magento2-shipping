<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment;

// Magento's object manager helper mocks the model's own dependencies.
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(Shipment::class)]
class ShipmentTest extends TestCase
{
    public function testAnEmptyDraftIsNone(): void
    {
        self::assertNull($this->shipment()->setDraft([])->getData(ShipmentInterface::DRAFT));
        self::assertSame([], $this->shipment()->setDraft(null)->getDraft());
    }

    public function testEmptyDetailsAndTrackingAreReadNotMissing(): void
    {
        $shipment = $this->shipment()->setDetails([])->setTracking([]);

        self::assertSame('[]', $shipment->getData(ShipmentInterface::DETAILS));
        self::assertSame('[]', $shipment->getData(ShipmentInterface::TRACKING));
        self::assertNull($this->shipment()->setDetails(null)->getData(ShipmentInterface::DETAILS));
    }

    public function testShippedItemsKeepTheirIds(): void
    {
        $shipment = $this->shipment()->setShipItems([7 => 2.0, 12 => 1.5]);

        self::assertSame('{"7":2,"12":1.5}', $shipment->getData(ShipmentInterface::SHIP_ITEMS));
        self::assertSame([7 => 2.0, 12 => 1.5], $shipment->getShipItems());
    }

    public function testPolishTextIsStoredAsItIs(): void
    {
        $shipment = $this->shipment()->setDraft(['description' => 'Zażółć gęślą jaźń']);

        self::assertStringContainsString('Zażółć', (string)$shipment->getData(ShipmentInterface::DRAFT));
        self::assertSame('Zażółć gęślą jaźń', $shipment->getDraft()['description']);
    }

    public function testWhatCannotBeStoredIsRefused(): void
    {
        $this->expectException(\JsonException::class);

        $this->shipment()->setDetails(['name' => "\xB1\x31"]);
    }

    private function shipment(): Shipment
    {
        return (new ObjectManager($this))->getObject(Shipment::class);
    }
}
