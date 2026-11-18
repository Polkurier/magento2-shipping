<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model;

use Magento\Framework\Serialize\Serializer\Json;
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
        self::assertNull($this->createShipment()->setDraft([])->getData(ShipmentInterface::FIELD_DRAFT));
        self::assertSame([], $this->createShipment()->setDraft(null)->getDraft());
    }

    public function testEmptyDetailsAndTrackingAreReadNotMissing(): void
    {
        $shipment = $this->createShipment()->setDetails([])->setTracking([]);

        self::assertSame('[]', $shipment->getData(ShipmentInterface::FIELD_DETAILS));
        self::assertSame('[]', $shipment->getData(ShipmentInterface::FIELD_TRACKING));
        self::assertNull($this->createShipment()->setDetails(null)->getData(ShipmentInterface::FIELD_DETAILS));
    }

    public function testShippedItemsKeepTheirIds(): void
    {
        $shipment = $this->createShipment()->setShipItems([7 => 2.0, 12 => 1.5]);

        self::assertSame('{"7":2,"12":1.5}', $shipment->getData(ShipmentInterface::FIELD_SHIP_ITEMS));
        self::assertSame([7 => 2.0, 12 => 1.5], $shipment->getShipItems());
    }

    public function testPolishTextIsReadBackAsItWasSet(): void
    {
        $shipment = $this->createShipment()->setDraft(['description' => 'Zażółć gęślą jaźń']);

        self::assertSame('Zażółć gęślą jaźń', $shipment->getDraft()['description']);
    }

    public function testWhatCannotBeStoredIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createShipment()->setDetails(['name' => "\xB1\x31"]);
    }

    private function createShipment(): Shipment
    {
        return (new ObjectManager($this))->getObject(Shipment::class, ['json' => new Json()]);
    }
}
