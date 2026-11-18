<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Status;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Api\ShipmentRepositoryInterface as MagentoShipmentRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\OrderLock;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderApplier;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderMatcher;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;
use Polkurier\Shipping\Model\Shipment\Status\TrackAdoptionService;
use Polkurier\Shipping\Model\ShipmentFactory;
use Polkurier\Shipping\Test\Unit\Support\MemoryLocks;
use Polkurier\Webservice\Entity\Order as RemoteOrder;

#[CoversClass(TrackAdoptionService::class)]
#[AllowMockObjectsWithoutExpectations]
class TrackAdoptionServiceTest extends TestCase
{
    /**
     * @var string[] what was done, in order
     */
    private array $happened = [];

    /**
     * @var ShipmentInterface[] the shipments saved
     */
    private array $saved = [];

    public function testAnOrderFoundByItsWaybillBecomesTheOrdersShipmentOnTheMagentoShipment(): void
    {
        $this->createService()->adopt(40, [' WB-1001 ', 'WB-1001']);

        self::assertSame([
            'take over tracks of 40',
            'add tracks to 40',
            'remark linked',
            'linked with 18015-2001',
            'event track',
        ], $this->happened);
        self::assertCount(1, $this->saved);
        $shipment = $this->saved[0];
        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertSame(40, $shipment->getShipmentId());
        self::assertSame(31, $shipment->getOrderId());
        self::assertSame('INPOST', $shipment->getCarrierCode());
        self::assertSame('000000031', $shipment->getExternalOrderId());
        self::assertSame(['WB-1001'], $shipment->getWaybills());
    }

    public function testOnAMagentoShipmentAnotherLiveOrderHoldsItGoesWithoutOne(): void
    {
        $this->createService(free: false)->adopt(40, ['WB-1001']);

        self::assertSame(['take over tracks of 40', 'linked with 18015-2001', 'event track'], $this->happened);
        self::assertNull($this->saved[0]->getShipmentId());
    }

    public function testAnOrderCancelledInThePanelIsLinkedAsCancelled(): void
    {
        $this->createService(cancelled: true)->adopt(40, ['WB-1001']);

        self::assertSame([
            'take over tracks of 40',
            'Linked with Polkurier order 18015-2001, cancelled in the Polkurier panel.',
        ], $this->happened);
        self::assertSame(ShipmentInterface::STATE_CANCELLED, $this->saved[0]->getState());
    }

    public function testANumberTheModuleHoldsIsLeftAlone(): void
    {
        $this->createService(heldWaybill: true)->adopt(40, ['WB-1001']);

        self::assertSame([], $this->happened);
        self::assertSame([], $this->saved);
    }

    public function testANumberNotOnTheAccountStaysAPlainTrack(): void
    {
        $this->createService(found: false)->adopt(40, ['WB-1001']);

        self::assertSame([], $this->happened);
        self::assertSame([], $this->saved);
    }

    public function testAnOrderMadeForAnotherStaysAPlainTrack(): void
    {
        $this->createService(madeFor: false)->adopt(40, ['WB-1001']);

        self::assertSame([], $this->happened);
        self::assertSame([], $this->saved);
    }

    public function testAnotherWaybillOfAnOrderAlreadyLinkedRefreshesIt(): void
    {
        $this->createService(heldOrder: 31)->adopt(40, ['WB-1002']);

        self::assertSame(['take over tracks of 40', 'refresh 8'], $this->happened);
        self::assertSame([], $this->saved);
    }

    public function testAnOrderAnotherMagentoOrderHoldsIsLeftAlone(): void
    {
        $this->createService(heldOrder: 30)->adopt(40, ['WB-1002']);

        self::assertSame([], $this->happened);
    }

    public function testAFailureIsLoggedAndTheOtherNumbersAreStillLinked(): void
    {
        $logged = [];
        $logger = $this->createStub(Logger::class);
        $logger->method('warning')->willReturnCallback(static function (string $message) use (&$logged): void {
            $logged[] = $message;
        });

        $this->createService(logger: $logger, failOn: 'W-BAD')->adopt(40, ['W-BAD', 'WB-1001']);

        self::assertSame(['A tracking number was not linked to its Polkurier order'], $logged);
        self::assertCount(1, $this->saved);
    }

    private function createService(
        bool $free = true,
        bool $cancelled = false,
        bool $heldWaybill = false,
        bool $found = true,
        bool $madeFor = true,
        ?int $heldOrder = null,
        ?Logger $logger = null,
        ?string $failOn = null,
    ): TrackAdoptionService {
        $remote = $this->createStub(RemoteOrder::class);
        $remote->method('getNumber')->willReturn('18015-2001');
        $remote->method('getCourier')->willReturn('INPOST');
        $remote->method('getDescription')->willReturn('Books');
        $remote->method('getPacks')->willReturn([]);
        $remote->method('getExternalOrderId')->willReturn('000000031');
        $matcher = $this->createStub(RemoteOrderMatcher::class);
        $matcher->method('findByWaybill')->willReturnCallback(
            static fn(string $waybill): ?RemoteOrder => $waybill === $failOn
                ? throw new LocalizedException(new Phrase('Polkurier is down'))
                : ($found ? $remote : null),
        );
        $matcher->method('isMadeFor')->willReturn($madeFor);
        $applier = $this->createStub(RemoteOrderApplier::class);
        $applier->method('apply')->willReturnCallback(
            static function (RemoteOrder $order, ShipmentInterface $shipment) use ($cancelled): void {
                $shipment->setPolkurierNumber((string)$order->getNumber())
                    ->setWaybills(['WB-1001'])
                    ->setState($cancelled ? ShipmentInterface::STATE_CANCELLED : ShipmentInterface::STATE_CREATED);
            },
        );
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('findLatestByWaybill')
            ->willReturn($heldWaybill ? $this->createStub(ShipmentInterface::class) : null);
        $held = $this->createStub(ShipmentInterface::class);
        $held->method('getId')->willReturn(8);
        $held->method('getOrderId')->willReturn((int)$heldOrder);
        $finder->method('findByNumbers')->willReturn($heldOrder !== null ? [$held] : []);
        $factory = $this->createStub(ShipmentFactory::class);
        $factory->method('create')->willReturnCallback(
            fn(): Shipment => (new ObjectManager($this))->getObject(Shipment::class, ['json' => new Json()]),
        );
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(function (ShipmentInterface $shipment): ShipmentInterface {
            $this->saved[] = $shipment;

            return $shipment;
        });
        $magentoShipment = $this->createStub(MagentoShipmentModel::class);
        $magentoShipment->method('getOrderId')->willReturn(31);
        $magentoShipments = $this->createStub(MagentoShipmentRepository::class);
        $magentoShipments->method('get')->willReturn($magentoShipment);
        $order = $this->createStub(Order::class);
        $order->method('getEntityId')->willReturn(31);
        $order->method('getStoreId')->willReturn(1);
        $orderLoader = $this->createStub(OrderLoader::class);
        $orderLoader->method('get')->willReturn($order);
        $native = $this->createStub(NativeShipmentService::class);
        $native->method('getFreeShipments')->willReturn($free ? [40 => ['increment_id' => '000000027']] : []);
        $native->method('takeOverTracks')->willReturnCallback(function (Order $order, int $shipmentId): void {
            $this->happened[] = 'take over tracks of ' . $shipmentId;
        });
        $native->method('addTracks')->willReturnCallback(function (Order $order, int $shipmentId): void {
            $this->happened[] = 'add tracks to ' . $shipmentId;
        });
        $followUp = $this->createStub(NativeFollowUpService::class);
        $followUp->method('addLinkedRemark')->willReturnCallback(function (): void {
            $this->happened[] = 'remark linked';
        });
        $followUp->method('notePlaced')->willReturnCallback(
            function (Order $order, ShipmentInterface $shipment, bool $byHand): void {
                $this->happened[] = ($byHand ? 'linked with ' : 'placed ') . $shipment->getPolkurierNumber();
            },
        );
        $followUp->method('followLink')->willReturnCallback(
            function (ShipmentInterface $shipment, Order $order, string $source): void {
                $this->happened[] = 'event ' . $source;
            },
        );
        $followUp->method('addComment')->willReturnCallback(function (Order $order, Phrase $comment): void {
            $this->happened[] = (string)$comment;
        });
        $refresh = $this->createStub(StatusRefreshService::class);
        $refresh->method('refresh')->willReturnCallback(function (int $shipmentId) use ($held): ShipmentInterface {
            $this->happened[] = 'refresh ' . $shipmentId;

            return $held;
        });

        return new TrackAdoptionService(
            $matcher,
            $applier,
            $finder,
            $factory,
            $repository,
            $magentoShipments,
            $orderLoader,
            new OrderLock(new MemoryLocks()),
            $native,
            $followUp,
            $refresh,
            $logger ?? $this->createStub(Logger::class),
        );
    }
}
