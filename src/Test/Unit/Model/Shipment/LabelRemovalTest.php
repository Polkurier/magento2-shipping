<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipment;
use Magento\Sales\Model\Order\Shipment\Track;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\LabelRemoval;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;

#[CoversClass(LabelRemoval::class)]
#[AllowMockObjectsWithoutExpectations]
class LabelRemovalTest extends TestCase
{
    /**
     * @var string[] what happened, in order
     */
    private array $log = [];
    private MagentoShipment $magentoShipment;

    protected function setUp(): void
    {
        $this->magentoShipment = $this->getMockBuilder(MagentoShipment::class)->disableOriginalConstructor()
            ->onlyMethods(['getTracks'])->getMock();
        $this->magentoShipment->setData(['order_id' => 42, 'increment_id' => '000000009', 'shipping_label' => '%PDF', 'packages' => [1 => []]]);
        $this->magentoShipment->setId(56);
        $this->magentoShipment->method('getTracks')->willReturn([
            $this->track('WB-1', 'polkurier'),
            $this->track('WB-1', 'ups'),
            $this->track('TYPED', 'polkurier'),
        ]);
    }

    public function testTheShipmentStaysWithoutTheCancelledOrdersLabelPackagesAndWaybills(): void
    {
        $cancelled = $this->polkurier(7, ShipmentInterface::STATE_CANCELLED);

        self::assertSame('000000009', $this->removal([$cancelled])->clear($this->order(42), $cancelled));
        self::assertNull($this->magentoShipment->getData('shipping_label'));
        self::assertNull($this->magentoShipment->getData('packages'));
        self::assertSame(['delete polkurier WB-1', 'save 56'], $this->log, 'Other carriers\' tracks and numbers typed by hand stay.');
    }

    public function testAShipmentAnotherPolkurierOrderHoldsIsLeftAlone(): void
    {
        $cancelled = $this->polkurier(7, ShipmentInterface::STATE_CANCELLED);

        self::assertNull($this->removal([$cancelled, $this->polkurier(8, ShipmentInterface::STATE_CREATED)])->clear($this->order(42), $cancelled));
        self::assertSame([], $this->log);
    }

    public function testAShipmentOfAnotherOrderIsLeftAlone(): void
    {
        $cancelled = $this->polkurier(7, ShipmentInterface::STATE_CANCELLED);

        self::assertNull($this->removal([$cancelled])->clear($this->order(43), $cancelled));
        self::assertSame([], $this->log);
    }

    /**
     * @param Shipment[] $linked
     */
    private function removal(array $linked): LabelRemoval
    {
        $shipments = $this->createStub(ShipmentRepositoryInterface::class);
        $shipments->method('get')->willReturn($this->magentoShipment);
        $shipments->method('save')->willReturnCallback(function (MagentoShipment $shipment): MagentoShipment {
            $this->log[] = 'save ' . $shipment->getId();

            return $shipment;
        });
        $tracks = $this->createStub(ShipmentTrackRepositoryInterface::class);
        $tracks->method('delete')->willReturnCallback(function (Track $track): bool {
            $this->log[] = 'delete ' . $track->getCarrierCode() . ' ' . $track->getTrackNumber();

            return true;
        });
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('linkedTo')->willReturn($linked);

        return new LabelRemoval($shipments, $tracks, $finder);
    }

    private function polkurier(int $id, string $state): Shipment
    {
        $shipment = $this->getMockBuilder(Shipment::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $shipment->setData(['state' => $state, 'shipment_id' => 56, 'waybills' => 'WB-1'])->setId($id);

        return $shipment;
    }

    private function order(int $id): Order
    {
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $order->setData('entity_id', $id);

        return $order;
    }

    private function track(string $number, string $carrier): Track
    {
        $track = $this->getMockBuilder(Track::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $track->setData(['track_number' => $number, 'carrier_code' => $carrier]);

        return $track;
    }
}
