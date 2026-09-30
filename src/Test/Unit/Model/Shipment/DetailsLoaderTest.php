<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\Sdk\ShipmentGatewayInterface;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\DetailsLoader;
use Polkurier\Shipping\Model\Shipment\OrderDetails;
use Polkurier\Shipping\Model\Shipment\OrderSnapshot;
use Polkurier\Shipping\Model\Shipment\Reconciler;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Webservice\Response\Order;

#[CoversClass(DetailsLoader::class)]
#[AllowMockObjectsWithoutExpectations]
class DetailsLoaderTest extends TestCase
{
    public function testAMissingOrderCopyIsReadForThePageOnly(): void
    {
        $reconciler = $this->createStub(Reconciler::class);
        $reconciler->method('findByNumber')->willReturn($this->createStub(Order::class));
        $snapshot = $this->createStub(OrderSnapshot::class);
        $snapshot->method('apply')->willReturnCallback(static function (Order $order, ShipmentInterface $shipment): void {
            $shipment->setStatusLabel('Doręczona');
        });
        $resource = $this->createMock(ShipmentResource::class);
        $resource->expects(self::never())->method('updateColumns');
        $stored = $this->shipment();

        $loaded = (new DetailsLoader($reconciler, $snapshot, $resource, $this->createStub(ShipmentGatewayInterface::class), new OrderDetails(), new FixedDateTime()))
            ->ensure($stored);

        self::assertSame([], $loaded['errors']);
        self::assertSame('Doręczona', $loaded['shipment']->getStatusLabel());
        self::assertNull($stored->getStatusLabel(), 'Nothing is stored, and the loaded row is left as it was.');
    }

    private function shipment(): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);

        return $shipment->setId(3)
            ->setState(ShipmentInterface::STATE_CREATED)
            ->setPolkurierNumber('18015-2865')
            ->setTrackingSyncedAt(\gmdate('Y-m-d H:i:s'));
    }
}
