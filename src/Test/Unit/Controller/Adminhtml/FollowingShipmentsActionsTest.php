<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Discard;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\MassRefresh;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Pickup;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Refresh;
use Polkurier\Shipping\Model\Shipment\DetailsLoader;
use Polkurier\Shipping\Model\Shipment\NotPlaced;
use Polkurier\Shipping\Model\Shipment\Pickups;
use Polkurier\Shipping\Model\Shipment\RequestedShipments;
use Polkurier\Shipping\Test\Unit\Support\AdminActionTestCase;

/**
 * The actions that follow placed shipments: booking their collective pickup, updating their
 * statuses, and discarding one that wasn't placed.
 */
#[CoversClass(Pickup::class)]
#[CoversClass(MassRefresh::class)]
#[CoversClass(Refresh::class)]
#[CoversClass(Discard::class)]
#[AllowMockObjectsWithoutExpectations]
class FollowingShipmentsActionsTest extends AdminActionTestCase
{
    public function testAPickupWindowThatIsntOneGoesBackToTheFormWithTheShipments(): void
    {
        $this->params = ['ids' => '4,5', 'order_id' => '29', 'pickup_date' => '2026-10-07', 'pickup_time_from' => '14:00', 'pickup_time_to' => '11:00'];
        $pickups = $this->createMock(Pickups::class);
        $pickups->expects(self::never())->method('bookCollective');

        (new Pickup($this->context(), $this->requested([]), $pickups))->execute();

        self::assertSame(['*/*/pickupForm', ['ids' => '4,5', 'order_id' => 29]], $this->redirect);
        self::assertCount(1, $this->messages['error']);
    }

    public function testAPickupBookedForSomeSaysWhichAndWhyNotTheRest(): void
    {
        $this->params = ['pickup_date' => '2026-10-07', 'pickup_time_from' => '11:00', 'pickup_time_to' => '14:00'];
        $pickups = $this->createStub(Pickups::class);
        $pickups->method('bookCollective')->willReturn(['booked' => ['711400-26'], 'failed' => ['711400-27' => 'Already collected']]);

        (new Pickup($this->context(), $this->requested([]), $pickups))->execute();

        // One message, at the gravest of what it says.
        self::assertSame(['Collective pickup booked for: 711400-26. Not booked for 711400-27: Already collected'], $this->messages['warning']);
        self::assertSame(['polkurier/shipment/index', []], $this->redirect);
    }

    public function testAPickupBookedForNoneIsAnError(): void
    {
        $this->params = ['pickup_date' => '2026-10-07', 'pickup_time_from' => '11:00', 'pickup_time_to' => '14:00'];
        $pickups = $this->createStub(Pickups::class);
        $pickups->method('bookCollective')->willReturn(['booked' => [], 'failed' => ['711400-27' => 'Already collected']]);

        (new Pickup($this->context(), $this->requested([]), $pickups))->execute();

        self::assertSame(['Not booked for 711400-27: Already collected'], $this->messages['error']);
        self::assertArrayNotHasKey('success', $this->messages);
    }

    public function testUpdatingStatusesTakesThePlacedShipmentsAndSaysWhichFailed(): void
    {
        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('refreshStatus')->willReturnCallback(function (int $id): ShipmentInterface {
            if ($id === 6) {
                throw new LocalizedException(\__('Polkurier is not answering.'));
            }

            return $this->shipment($id, ShipmentInterface::STATE_CREATED, '711400-' . $id);
        });
        $selected = [
            $this->shipment(4, ShipmentInterface::STATE_CREATED, '711400-4'),
            $this->shipment(5, ShipmentInterface::STATE_FAILED, null),
            $this->shipment(6, ShipmentInterface::STATE_CREATED, '711400-6'),
        ];

        (new MassRefresh($this->context(), $this->requested($selected), $management))->execute();

        self::assertSame(['1 shipment(s) updated. Not updated: 711400-6: Polkurier is not answering.'], $this->messages['warning']);
    }

    public function testUpdatingOneShipmentSaysItsStatusAndReadsItsScans(): void
    {
        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('refreshStatus')->willReturn($this->shipment(4, ShipmentInterface::STATE_CREATED, '711400-4', 'Doręczona'));
        $details = $this->createMock(DetailsLoader::class);
        $details->expects(self::once())->method('refreshTracking');

        (new Refresh($this->context(), $this->requested([], $this->shipment(4, ShipmentInterface::STATE_CREATED, '711400-4')), $management, $details))->execute();

        self::assertSame(['Polkurier order 711400-4: Doręczona.'], $this->messages['success']);
    }

    public function testScansThatCantBeReadDontUndoTheStatus(): void
    {
        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('refreshStatus')->willReturn($this->shipment(4, ShipmentInterface::STATE_CREATED, '711400-4', 'Doręczona'));
        $details = $this->createStub(DetailsLoader::class);
        $details->method('refreshTracking')->willThrowException(new LocalizedException(\__('The carrier\'s scans could not be read.')));

        (new Refresh($this->context(), $this->requested([], $this->shipment(4, ShipmentInterface::STATE_CREATED, '711400-4')), $management, $details))->execute();

        self::assertSame(['Polkurier order 711400-4: Doręczona. The carrier\'s scans could not be read.'], $this->messages['warning']);
    }

    public function testADiscardedShipmentGoesBackToItsOrderNotToItsGonePage(): void
    {
        $notPlaced = $this->createMock(NotPlaced::class);
        $notPlaced->expects(self::once())->method('discard')->with(4);

        (new Discard($this->context(), $this->requested([], $this->shipment(4, ShipmentInterface::STATE_FAILED, null)), $notPlaced))->execute();

        self::assertSame(['The shipment that wasn\'t placed was discarded.'], $this->messages['success']);
        self::assertSame(['sales/order/view', ['order_id' => 29]], $this->redirect);
    }

    public function testAShipmentThatCantBeDiscardedSaysWhy(): void
    {
        $notPlaced = $this->createStub(NotPlaced::class);
        $notPlaced->method('discard')->willThrowException(new LocalizedException(\__('Only a shipment that wasn\'t placed can be discarded.')));

        (new Discard($this->context(), $this->requested([], $this->shipment(4, ShipmentInterface::STATE_CREATED, '711400-4')), $notPlaced))->execute();

        self::assertSame(['Only a shipment that wasn\'t placed can be discarded.'], $this->messages['error']);
    }

    /**
     * @param ShipmentInterface[] $selected
     */
    private function requested(array $selected, ?ShipmentInterface $one = null): RequestedShipments
    {
        $requested = $this->createStub(RequestedShipments::class);
        $requested->method('getSelected')->willReturn($selected);
        if ($one !== null) {
            $requested->method('getOne')->willReturn($one);
        }

        return $requested;
    }

    private function shipment(int $id, string $state, ?string $number, string $status = ''): ShipmentInterface
    {
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getId')->willReturn($id);
        $shipment->method('getOrderId')->willReturn(29);
        $shipment->method('getState')->willReturn($state);
        $shipment->method('getPolkurierNumber')->willReturn($number);
        $shipment->method('getStatusLabel')->willReturn($status);

        return $shipment;
    }
}
