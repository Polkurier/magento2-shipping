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
use Polkurier\Shipping\Controller\Adminhtml\Shipment\RequestedShipments;
use Polkurier\Shipping\Model\Shipment\Details\DetailsLoader;
use Polkurier\Shipping\Model\Shipment\PickupService;
use Polkurier\Shipping\Model\Shipment\Placement\FailedShipmentManager;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;
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
        $this->params = [
            'ids' => '4,5',
            'order_id' => '29',
            'pickup_date' => '2026-10-07',
            'pickup_time_from' => '14:00',
            'pickup_time_to' => '11:00',
        ];
        $pickups = $this->createMock(PickupService::class);
        $pickups->expects(self::never())->method('bookCollective');

        (new Pickup(
            $this->context(),
            $this->requested([]),
            $pickups,
            $this->flashMessageCollectorFactory(),
        ))->execute();

        self::assertSame(['*/*/pickupForm', ['ids' => '4,5', 'order_id' => 29]], $this->redirect);
        self::assertCount(1, $this->messages['error']);
    }

    public function testIdsSentAsAListAreNotCarriedBackToTheForm(): void
    {
        $this->params = [
            'ids' => ['4', '5'],
            'pickup_date' => '2026-10-07',
            'pickup_time_from' => '14:00',
            'pickup_time_to' => '11:00',
        ];

        (new Pickup(
            $this->context(),
            $this->requested([]),
            $this->createStub(PickupService::class),
            $this->flashMessageCollectorFactory(),
        ))->execute();

        self::assertSame(['*/*/pickupForm', []], $this->redirect);
    }

    public function testAPickupBookedForSomeSaysWhichAndWhyNotTheRest(): void
    {
        $this->params = ['pickup_date' => '2026-10-07', 'pickup_time_from' => '11:00', 'pickup_time_to' => '14:00'];
        $pickups = $this->createStub(PickupService::class);
        $pickups->method('bookCollective')
            ->willReturn(['booked' => ['711400-26'], 'failed' => ['711400-27' => 'Already collected']]);

        (new Pickup(
            $this->context(),
            $this->requested([]),
            $pickups,
            $this->flashMessageCollectorFactory(),
        ))->execute();

        // One message, at the gravest of what it says.
        self::assertSame(
            ['Collective pickup booked for: 711400-26. Not booked for 711400-27: Already collected'],
            $this->messages['warning'],
        );
        self::assertSame(['polkurier/shipment/index', []], $this->redirect);
    }

    public function testAPickupBookedForNoneIsAnError(): void
    {
        $this->params = ['pickup_date' => '2026-10-07', 'pickup_time_from' => '11:00', 'pickup_time_to' => '14:00'];
        $pickups = $this->createStub(PickupService::class);
        $pickups->method('bookCollective')
            ->willReturn(['booked' => [], 'failed' => ['711400-27' => 'Already collected']]);

        (new Pickup(
            $this->context(),
            $this->requested([]),
            $pickups,
            $this->flashMessageCollectorFactory(),
        ))->execute();

        self::assertSame(['Not booked for 711400-27: Already collected'], $this->messages['error']);
        self::assertArrayNotHasKey('success', $this->messages);
    }

    public function testUpdatingStatusesTakesThePlacedShipmentsTogetherAndSaysWhichFailed(): void
    {
        $refresher = $this->createMock(StatusRefreshService::class);
        $refresher->expects(self::once())->method('refreshMany')->with([4, 6])->willReturn([
            4 => $this->shipment(4, ShipmentInterface::STATE_CREATED, '711400-4'),
            6 => new LocalizedException(__('Polkurier is not answering.')),
        ]);
        $selected = [
            $this->shipment(4, ShipmentInterface::STATE_CREATED, '711400-4'),
            $this->shipment(5, ShipmentInterface::STATE_FAILED, null),
            $this->shipment(6, ShipmentInterface::STATE_CREATED, '711400-6'),
        ];

        (new MassRefresh(
            $this->context(),
            $this->requested($selected),
            $refresher,
            $this->flashMessageCollectorFactory(),
        ))->execute();

        self::assertSame(
            ['1 shipment(s) updated. Not updated: 711400-6: Polkurier is not answering.'],
            $this->messages['warning'],
        );
    }

    public function testUpdatingOneShipmentSaysItsStatusAndReadsItsScans(): void
    {
        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('refreshStatus')
            ->willReturn($this->shipment(4, ShipmentInterface::STATE_CREATED, '711400-4', 'Doręczona'));
        $details = $this->createMock(DetailsLoader::class);
        $details->expects(self::once())->method('refreshTracking');

        (new Refresh(
            $this->context(),
            $this->requested([], $this->shipment(4, ShipmentInterface::STATE_CREATED, '711400-4')),
            $management,
            $details,
            $this->flashMessageCollectorFactory(),
        ))->execute();

        self::assertSame(['Polkurier order 711400-4: Doręczona.'], $this->messages['success']);
    }

    public function testScansThatCantBeReadDontUndoTheStatus(): void
    {
        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('refreshStatus')
            ->willReturn($this->shipment(4, ShipmentInterface::STATE_CREATED, '711400-4', 'Doręczona'));
        $details = $this->createStub(DetailsLoader::class);
        $details->method('refreshTracking')
            ->willThrowException(new LocalizedException(__('The carrier\'s scans could not be read.')));

        (new Refresh(
            $this->context(),
            $this->requested([], $this->shipment(4, ShipmentInterface::STATE_CREATED, '711400-4')),
            $management,
            $details,
            $this->flashMessageCollectorFactory(),
        ))->execute();

        self::assertSame(
            ['Polkurier order 711400-4: Doręczona. The carrier\'s scans could not be read.'],
            $this->messages['warning'],
        );
    }

    public function testADiscardedShipmentGoesBackToItsOrderNotToItsGonePage(): void
    {
        $failedShipmentManager = $this->createMock(FailedShipmentManager::class);
        $failedShipmentManager->expects(self::once())->method('discard')->with(4);

        (new Discard(
            $this->context(),
            $this->requested([], $this->shipment(4, ShipmentInterface::STATE_FAILED, null)),
            $failedShipmentManager,
        ))->execute();

        self::assertSame(['The shipment that wasn\'t placed was discarded.'], $this->messages['success']);
        self::assertSame(['sales/order/view', ['order_id' => 29]], $this->redirect);
    }

    public function testAShipmentThatCantBeDiscardedSaysWhy(): void
    {
        $failedShipmentManager = $this->createStub(FailedShipmentManager::class);
        $failedShipmentManager->method('discard')
            ->willThrowException(new LocalizedException(__('Only a shipment that wasn\'t placed can be discarded.')));

        (new Discard(
            $this->context(),
            $this->requested([], $this->shipment(4, ShipmentInterface::STATE_CREATED, '711400-4')),
            $failedShipmentManager,
        ))->execute();

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
