<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Observer\Adminhtml;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Observer\Adminhtml\LinkLabelledShipmentObserver;

#[CoversClass(LinkLabelledShipmentObserver::class)]
#[AllowMockObjectsWithoutExpectations]
class LinkLabelledShipmentObserverTest extends TestCase
{
    public function testThePolkurierOrderOfALabelIsLinkedToTheSavedShipment(): void
    {
        $magentoShipment = $this->createMagentoShipment(52)->setData(NativeLabelService::FIELD_LINK, 9);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->expects(self::once())->method('setShipmentId')->with(52)->willReturnSelf();
        $shipments = $this->createMock(ShipmentRepositoryInterface::class);
        $shipments->method('get')->with(9)->willReturn($shipment);
        $shipments->expects(self::once())->method('save')->with($shipment);
        $nativeShipments = $this->createMock(NativeShipmentService::class);
        $nativeShipments->expects(self::once())->method('release')->with(52);
        $followUp = $this->createMock(NativeFollowUpService::class);
        $followUp->expects(self::once())->method('addLinkedRemark')->with($shipment);

        (new LinkLabelledShipmentObserver($shipments, $nativeShipments, $followUp))
            ->execute($this->createObserver($magentoShipment));

        self::assertNull($magentoShipment->getData(NativeLabelService::FIELD_LINK), 'Linked once.');
    }

    public function testAShipmentSavedWithoutALabelIsLeftAlone(): void
    {
        $shipments = $this->createMock(ShipmentRepositoryInterface::class);
        $shipments->expects(self::never())->method('save');

        (new LinkLabelledShipmentObserver(
            $shipments,
            $this->createStub(NativeShipmentService::class),
            $this->createStub(NativeFollowUpService::class),
        ))->execute($this->createObserver($this->createMagentoShipment(52)));
    }

    private function createMagentoShipment(int $id): MagentoShipmentModel
    {
        /** @var MagentoShipmentModel $magentoShipment */
        $magentoShipment = (new ObjectManager($this))->getObject(MagentoShipmentModel::class);

        return $magentoShipment->setId($id);
    }

    private function createObserver(MagentoShipmentModel $magentoShipment): Observer
    {
        return new Observer(['event' => new Event(['shipment' => $magentoShipment])]);
    }
}
