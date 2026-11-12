<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Sales;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\ShipmentDocumentFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\Native\ReservedNumberRegistry;
use Polkurier\Shipping\Plugin\Sales\ShipmentDocumentFactoryPlugin;

#[CoversClass(ShipmentDocumentFactoryPlugin::class)]
#[CoversClass(ReservedNumberRegistry::class)]
// Real shipments, but for their constructor: only their number is looked at.
#[AllowMockObjectsWithoutExpectations]
class ShipmentDocumentFactoryPluginTest extends TestCase
{
    public function testTheShipmentMadeForTheOrderTakesTheNumberExpected(): void
    {
        $numbers = new ReservedNumberRegistry();
        $numbers->expect(42, '000000077');
        $plugin = new ShipmentDocumentFactoryPlugin($numbers);

        $other = $plugin->afterCreate($this->createFactory(), $this->createShipment(), $this->createOrder(7));
        $made = $plugin->afterCreate($this->createFactory(), $this->createShipment(), $this->createOrder(42));
        $next = $plugin->afterCreate($this->createFactory(), $this->createShipment(), $this->createOrder(42));

        self::assertNull($other->getIncrementId(), 'Another order\'s shipment is numbered by Magento.');
        self::assertSame('000000077', $made->getIncrementId());
        self::assertNull($next->getIncrementId(), 'The number goes to one shipment only.');
    }

    public function testAShipmentNumberedAlreadyLeavesTheNumberToTheNext(): void
    {
        $numbers = new ReservedNumberRegistry();
        $numbers->expect(42, '000000077');
        $plugin = new ShipmentDocumentFactoryPlugin($numbers);

        $numbered = $plugin->afterCreate(
            $this->createFactory(),
            $this->createShipment()->setIncrementId('000000070'),
            $this->createOrder(42),
        );
        $made = $plugin->afterCreate($this->createFactory(), $this->createShipment(), $this->createOrder(42));

        self::assertSame('000000070', $numbered->getIncrementId());
        self::assertSame('000000077', $made->getIncrementId());
    }

    private function createShipment(): Shipment
    {
        return $this->getMockBuilder(Shipment::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
    }

    private function createOrder(int $id): OrderInterface
    {
        $order = $this->createStub(OrderInterface::class);
        $order->method('getEntityId')->willReturn($id);

        return $order;
    }

    private function createFactory(): ShipmentDocumentFactory
    {
        return $this->createStub(ShipmentDocumentFactory::class);
    }
}
