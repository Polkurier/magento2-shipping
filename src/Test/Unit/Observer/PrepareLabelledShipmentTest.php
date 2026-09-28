<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Shipment\MagentoShipment;
use Polkurier\Shipping\Model\Shipment\NativeLabel;
use Polkurier\Shipping\Observer\PrepareLabelledShipment;

#[CoversClass(PrepareLabelledShipment::class)]
#[AllowMockObjectsWithoutExpectations]
class PrepareLabelledShipmentTest extends TestCase
{
    public function testLabelTracksAreRetitledForCustomersAndNotDuplicated(): void
    {
        $existing = $this->track(5, 'WB-1', 'DPD kurier');
        $again = $this->track(null, 'WB-1', 'Polkurier');
        $new = $this->track(null, 'WB-2', 'Polkurier');
        $typed = $this->track(null, 'WB-3', 'Typed by hand');
        $other = $this->track(null, 'X-1', 'Polkurier', 'ups');
        $shipment = $this->shipment([$existing, $again, $new, $typed, $other]);

        $this->observer()->execute($this->event($shipment));

        self::assertSame([$existing, $new, $typed, $other], $shipment->getData('kept'));
        self::assertSame('Kurier DPD', $new->getData('title'), 'The shipping method, as customers know it.');
        self::assertSame('Typed by hand', $typed->getData('title'), 'A title the merchant typed stays.');
        self::assertSame('Polkurier', $other->getData('title'), 'Other carriers are left alone.');
    }

    public function testALabelThatWasNotAvailableIsNotStored(): void
    {
        $shipment = $this->shipment([]);
        $shipment->setData(NativeLabel::NO_LABEL, true);
        $shipment->setData('shipping_label', '%PDF-empty');

        $this->observer()->execute($this->event($shipment));

        self::assertFalse($shipment->hasData('shipping_label'));
        self::assertFalse($shipment->hasData(NativeLabel::NO_LABEL));
    }

    private function observer(): PrepareLabelledShipment
    {
        $config = $this->createStub(Config::class);
        $config->method('getTitle')->willReturn('Polkurier');
        $titles = $this->createStub(MagentoShipment::class);
        $titles->method('getTrackTitle')->willReturn('Kurier DPD');

        return new PrepareLabelledShipment($config, $titles);
    }

    private function event(Shipment $shipment): Observer
    {
        return new Observer(['event' => new Event(['shipment' => $shipment])]);
    }

    /**
     * @param Track[] $tracks
     */
    private function shipment(array $tracks): Shipment
    {
        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn('polkurier_DPD');
        $shipment = $this->getMockBuilder(Shipment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTracks', 'setTracks', 'getOrder', 'getStoreId'])
            ->getMock();
        $shipment->method('getTracks')->willReturn($tracks);
        $shipment->method('getOrder')->willReturn($order);
        $shipment->method('getStoreId')->willReturn(1);
        $shipment->method('setTracks')->willReturnCallback(function (array $kept) use ($shipment) {
            return $shipment->setData('kept', $kept);
        });

        return $shipment;
    }

    private function track(?int $id, string $number, string $title, string $carrier = 'polkurier'): Track
    {
        $track = $this->getMockBuilder(Track::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityId', 'getTrackNumber', 'getCarrierCode', 'getTitle', 'setTitle'])
            ->getMock();
        $track->method('getEntityId')->willReturn($id);
        $track->method('getTrackNumber')->willReturn($number);
        $track->method('getCarrierCode')->willReturn($carrier);
        $track->method('getTitle')->willReturnCallback(fn() => $track->getData('title'));
        $track->method('setTitle')->willReturnCallback(fn(string $title) => $track->setData('title', $title));
        $track->setData('title', $title);

        return $track;
    }
}
