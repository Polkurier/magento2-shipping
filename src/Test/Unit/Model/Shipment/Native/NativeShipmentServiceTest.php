<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Native;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Api\Data\ShipmentItemCreationInterface;
use Magento\Sales\Api\Data\ShipmentItemCreationInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentTrackCreationInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Magento\Sales\Api\Data\ShipmentTrackInterfaceFactory;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use Magento\Sales\Model\Order\Shipment\ItemCreation;
use Magento\Sales\Model\Order\Shipment\Track;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\Draft\Item;
use Polkurier\Shipping\Model\Shipment\Draft\Pack;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\Native\ReservedNumberRegistry;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;

#[CoversClass(NativeShipmentService::class)]
#[AllowMockObjectsWithoutExpectations]
class NativeShipmentServiceTest extends TestCase
{
    private const array ITEMS = [
        5 => [
            'name' => 'Impulse Duffle',
            'sku' => '24-UB02',
            'product_id' => 7,
            'unit' => 74.0,
            'weight' => 1.2,
            'qty' => 2.0,
            'qty_shipped' => 0.0,
            'qty_to_ship' => 2.0,
        ],
        6 => [
            'name' => 'Dash Digital Watch',
            'sku' => '24-MG02',
            'product_id' => 40,
            'unit' => 92.0,
            'weight' => 0.1,
            'qty' => 1.0,
            'qty_shipped' => 0.0,
            'qty_to_ship' => 1.0,
        ],
    ];

    /**
     * @var ShipmentInterface[] Polkurier orders linked to the order's Magento shipments
     */
    private array $linked = [];

    private ReservedNumberRegistry $reservedNumbers;

    /**
     * @var array<int, ShipmentTrackInterface[]> tracks by Magento shipment id
     */
    private array $magentoShipments = [];

    /**
     * @var ShipmentTrackInterface[]
     */
    private array $deletedTracks = [];

    /**
     * @var string[]
     */
    private array $savedTrackNumbers = [];

    public function testTheChosenQuantitiesAreKeptWithoutTheItemsLeftOut(): void
    {
        $qtys = $this->magentoShipment()->getQuantities($this->order(), [
            (new Item())->setOrderItemId(5)->setQty(1.0),
            (new Item())->setOrderItemId(6)->setQty(0.0),
        ]);

        self::assertSame([5 => 1.0], $qtys);
    }

    public function testATrackIsTitledWithTheCarrierItWentWith(): void
    {
        $shipments = $this->magentoShipment();

        self::assertSame(
            'Kurier InPost',
            $shipments->getTrackTitle($this->order(), 'INPOST'),
            'The carrier\'s label, not the description with the point.',
        );
        self::assertSame(
            'Kurier DPD',
            $shipments->getTrackTitle($this->order(), 'DPD'),
            'Without settings: the method the customer chose.',
        );
        self::assertSame(
            'GLS',
            $shipments->getTrackTitle($this->order(), 'GLS'),
            'Another carrier without settings: its code.',
        );
    }

    public function testNoItemsMeansEverythingLeft(): void
    {
        self::assertSame([], $this->magentoShipment()->getQuantities($this->order(), []));
    }

    public function testEverythingLeftIsWhatTheOrderHasLeftToShip(): void
    {
        self::assertSame([5 => 2.0, 6 => 1.0], $this->magentoShipment()->getQuantitiesLeft($this->order()));
    }

    public function testMoreThanIsLeftIsRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('You can ship at most 1 of "Dash Digital Watch".');

        $this->magentoShipment()->getQuantities($this->order(), [(new Item())->setOrderItemId(6)->setQty(2.0)]);
    }

    public function testAnItemOfAnotherOrderIsRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The order has no item 99 to ship.');

        $this->magentoShipment()->getQuantities($this->order(), [(new Item())->setOrderItemId(99)->setQty(1.0)]);
    }

    public function testShippingNothingIsRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Choose at least one item to ship.');

        $this->magentoShipment()->getQuantities($this->order(), [(new Item())->setOrderItemId(5)->setQty(0.0)]);
    }

    public function testWithItemsLeftANewMagentoShipmentIsMade(): void
    {
        $target = $this->magentoShipment()->getTarget(
            $this->order(),
            (new Draft())->setItems([(new Item())->setOrderItemId(5)->setQty(1.0)]),
        );

        self::assertSame(['qtys' => [5 => 1.0], 'shipment_id' => null, 'packages' => []], $target);
    }

    public function testWithNothingLeftTheOrderGoesOnTheLatestShipmentWithoutAPolkurierOrder(): void
    {
        $this->linked = [
            $this->polkurier(10, 3, ShipmentInterface::STATE_CREATED),
            $this->polkurier(11, 4, ShipmentInterface::STATE_CANCELLED),
        ];
        $order = $this->order(canShip: false, shipments: [3 => '000000003', 4 => '000000004', 5 => '000000005']);

        self::assertSame([
            4 => ['increment_id' => '000000004', 'replaces' => 'POL11'],
            5 => ['increment_id' => '000000005', 'replaces' => null],
        ], $this->magentoShipment()->getFreeShipments($order));
        self::assertSame(
            ['qtys' => [], 'shipment_id' => 5, 'packages' => []],
            $this->magentoShipment()->getTarget($order, new Draft()),
        );
    }

    public function testEverythingShippedWithAPolkurierOrderEachLeavesNowhereToGo(): void
    {
        $this->linked = [$this->polkurier(10, 3, ShipmentInterface::STATE_CREATED)];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('To send a replacement, cancel the Polkurier order it replaces first.');

        $this->magentoShipment()->getTarget($this->order(canShip: false, shipments: [3 => '000000003']), new Draft());
    }

    public function testOnlyThePolkurierTracksOfTheWaybillsAreRemoved(): void
    {
        $tracks = [];
        foreach ([['WB-1', 'polkurier'], ['WB-1', 'ups'], ['TYPED', 'polkurier']] as [$number, $carrier]) {
            $track = $this->getMockBuilder(Track::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
            $tracks[] = $track->setData(['track_number' => $number, 'carrier_code' => $carrier]);
        }
        $shipment = $this->createStub(MagentoShipmentModel::class);
        $shipment->method('getTracks')->willReturn($tracks);

        $this->magentoShipment()->removeTracks($shipment, ['WB-1']);

        self::assertSame([$tracks[0]], $this->deletedTracks, 'Other carriers\' tracks and numbers typed by hand stay.');
    }

    public function testAReplacementGoesOnTheMagentoShipmentOfTheCancelledOrder(): void
    {
        $cancelled = $this->polkurier(11, 4, ShipmentInterface::STATE_CANCELLED, ['WB-OLD']);
        $this->linked = [$cancelled];
        $oldTrack = $this->track('WB-OLD');
        $otherTrack = $this->track('OTHER', 'dhl');
        $this->magentoShipments[4] = [$oldTrack, $otherTrack];
        $replacement = $this->createStub(ShipmentInterface::class);
        $replacement->method('getWaybills')->willReturn(['WB-NEW']);
        $replacement->method('getCarrierCode')->willReturn('DPD');

        $id = $this->magentoShipment()->ship(
            $this->order(canShip: false, shipments: [4 => '000000004']),
            $replacement,
            [],
            4,
        );

        self::assertSame(4, $id);
        self::assertSame(4, $cancelled->getShipmentId(), 'The shipment keeps every Polkurier order it has had.');
        self::assertSame([$oldTrack], $this->deletedTracks, 'Only its waybills leave the tracking.');
        self::assertSame(['WB-NEW'], $this->savedTrackNumbers);
    }

    public function testParcelsBecomeMagentoPackagesWithWhatEachHolds(): void
    {
        $packs = [
            (new Pack())->setPackType('ST')->setLength(40)->setWidth(30)->setHeight(20)->setWeight(2.5)->setAmount(2)
                ->setItems([(new Item())->setOrderItemId(5)->setQty(1.0)]),
            (new Pack())->setPackType('NST')->setLength(20)->setWidth(20)->setHeight(10)->setWeight(0.5)->setAmount(1)
                ->setItems([(new Item())->setOrderItemId(6)->setQty(1.0)]),
        ];

        $packages = $this->magentoShipment()->getPackages($this->order(), $packs, []);

        self::assertCount(3, $packages, 'A package for each of the identical parcels.');
        self::assertSame([1, 2, 3], \array_keys($packages));
        self::assertSame('ST', $packages[1]['params']['container']);
        self::assertSame('KILOGRAM', $packages[1]['params']['weight_units']);
        self::assertSame(74.0, $packages[2]['params']['customs_value']);
        self::assertSame([
            'qty' => 1.0,
            'customs_value' => 74.0,
            'price' => 74.0,
            'name' => 'Impulse Duffle',
            'weight' => 1.2,
            'product_id' => 7,
            'order_item_id' => 5,
        ], $packages[1]['items'][5]);
        self::assertSame([6], \array_keys($packages[3]['items']));
    }

    public function testParcelsCannotHoldMoreThanIsShipped(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The parcels hold more of "Impulse Duffle" than the shipment carries.');

        $this->magentoShipment()->getPackages($this->order(), [
            (new Pack())->setPackType('ST')->setAmount(2)->setItems([(new Item())->setOrderItemId(5)->setQty(1.0)]),
        ], [5 => 1.0]);
    }

    public function testParcelsCannotHoldItemsLeftOutOfTheShipment(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('A parcel holds an item the shipment doesn\'t carry.');

        $this->magentoShipment()->getPackages($this->order(), [
            (new Pack())->setPackType('ST')->setAmount(1)->setItems([(new Item())->setOrderItemId(6)->setQty(1.0)]),
        ], [5 => 1.0]);
    }

    public function testMagentoShipsOnlyTheQuantitiesGivenAndEmailsNobody(): void
    {
        $shipOrder = $this->createMock(ShipOrderInterface::class);
        $shipOrder->expects(self::once())->method('execute')->with(
            42,
            self::callback(static fn(array $items): bool => \count($items) === 1
                && $items[0]->getOrderItemId() === 5 && $items[0]->getQty() === 1.0),
            false,
        )->willReturn('77');
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getWaybills')->willReturn(['WB-1']);
        $shipment->method('getCarrierCode')->willReturn('DPD');

        self::assertSame(77, $this->magentoShipment($shipOrder)->ship($this->order(), $shipment, [5 => 1.0]));
    }

    public function testTheMagentoShipmentMadeTakesTheNumberTheOrderWasSentWith(): void
    {
        $seen = null;
        $shipOrder = $this->createStub(ShipOrderInterface::class);
        $shipOrder->method('execute')->willReturnCallback(function () use (&$seen): string {
            // What the plugin on Magento's shipment factory gives the shipment it makes.
            $seen = $this->reservedNumbers->take(42);

            return '77';
        });
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getWaybills')->willReturn(['WB-1']);
        $shipment->method('getCarrierCode')->willReturn('DPD');
        $shipment->method('getReservedShipmentNumber')->willReturn('000000077');

        $this->magentoShipment($shipOrder)->ship($this->order(), $shipment, [5 => 1.0]);

        self::assertSame('000000077', $seen);
        self::assertNull($this->reservedNumbers->take(42), 'Not left for another shipment.');
    }

    public function testACustomerCanBeEmailedAboutAShipmentNobodyEmailedAbout(): void
    {
        $shipment = $this->getMockBuilder(MagentoShipmentModel::class)->disableOriginalConstructor()
            ->onlyMethods([])->getMock();

        self::assertTrue(NativeShipmentService::isEmailOpen($shipment));
        self::assertTrue(NativeShipmentService::isEmailOpen($shipment->setData(['send_email' => '0'])));
        self::assertFalse(NativeShipmentService::isEmailOpen($shipment->setData(['send_email' => '1'])), 'Queued.');
        self::assertFalse(NativeShipmentService::isEmailOpen($shipment->setData(['email_sent' => '1'])), 'Sent.');
    }

    protected function setUp(): void
    {
        $this->reservedNumbers = new ReservedNumberRegistry();
    }

    private function magentoShipment(?ShipOrderInterface $shipOrder = null): NativeShipmentService
    {
        $itemFactory = $this->createStub(ShipmentItemCreationInterfaceFactory::class);
        $itemFactory->method('create')->willReturnCallback(
            fn(): ShipmentItemCreationInterface => $this->getMockBuilder(ItemCreation::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock(),
        );
        $amounts = $this->createStub(OrderAmountCalculator::class);
        $amounts->method('getItems')->willReturn(self::ITEMS);
        $tracks = $this->createStub(ShipmentTrackCreationInterfaceFactory::class);
        $tracks->method('create')->willReturnCallback(
            fn() => $this->getMockBuilder(\Magento\Sales\Model\Order\Shipment\TrackCreation::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock(),
        );

        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturnCallback(function (): Collection {
            $filters = [];
            $collection = $this->createStub(Collection::class);
            $collection->method('addFieldToFilter')->willReturnCallback(
                function (string $field, array $condition) use (&$filters, $collection): Collection {
                    $filters[$field] = $condition;

                    return $collection;
                },
            );
            $collection->method('setOrder')->willReturnSelf();
            $collection->method('getItems')->willReturnCallback(function () use (&$filters): array {
                return \array_values(\array_filter(
                    $this->linked,
                    static function (ShipmentInterface $shipment) use (&$filters): bool {
                        $values = [
                            ShipmentInterface::FIELD_SHIPMENT_ID => $shipment->getShipmentId(),
                            ShipmentInterface::FIELD_STATE => $shipment->getState(),
                        ];
                        foreach ($filters as $field => $condition) {
                            $matches = match (\array_key_first($condition)) {
                                'eq' => $values[$field] === $condition['eq'],
                                'in' => \in_array($values[$field], $condition['in'], true),
                                'nin' => !\in_array($values[$field], $condition['nin'], true),
                                default => throw new \LogicException('The test collection does not know this condition.'),
                            };
                            if (!$matches) {
                                return false;
                            }
                        }

                        return true;
                    },
                ));
            });

            return $collection;
        });
        $magentoRepository = $this->createStub(ShipmentRepositoryInterface::class);
        $magentoRepository->method('get')->willReturnCallback(function (int $id): MagentoShipmentModel {
            $shipment = $this->createStub(MagentoShipmentModel::class);
            $shipment->method('getTracks')->willReturn($this->magentoShipments[$id] ?? []);

            return $shipment;
        });
        $trackRepository = $this->createStub(ShipmentTrackRepositoryInterface::class);
        $trackRepository->method('delete')->willReturnCallback(function (ShipmentTrackInterface $track): bool {
            $this->deletedTracks[] = $track;

            return true;
        });
        $trackRepository->method('save')->willReturnCallback(
            function (ShipmentTrackInterface $track): ShipmentTrackInterface {
                $this->savedTrackNumbers[] = (string)$track->getTrackNumber();

                return $track;
            },
        );
        $trackFactory = $this->createStub(ShipmentTrackInterfaceFactory::class);
        $trackFactory->method('create')->willReturnCallback(
            fn(): Track => $this->getMockBuilder(Track::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock(),
        );

        return new NativeShipmentService(
            $shipOrder ?? $this->createStub(ShipOrderInterface::class),
            $itemFactory,
            $amounts,
            $tracks,
            $trackFactory,
            $trackRepository,
            $magentoRepository,
            new ShipmentFinder($collections),
            $this->carrierConfigs(),
            $this->reservedNumbers,
        );
    }

    /**
     * @param string[] $waybills
     */
    private function polkurier(int $id, int $magentoShipmentId, string $state, array $waybills = []): ShipmentInterface
    {
        $shipment = $this->getMockBuilder(Shipment::class)->disableOriginalConstructor()
            ->onlyMethods(['getState', 'getPolkurierNumber', 'getWaybills'])->getMock();
        $shipment->method('getState')->willReturn($state);
        $shipment->method('getPolkurierNumber')->willReturn('POL' . $id);
        $shipment->method('getWaybills')->willReturn($waybills);
        $shipment->setId($id);
        $shipment->setShipmentId($magentoShipmentId);

        return $shipment;
    }

    private function track(string $number, string $carrierCode = 'polkurier'): ShipmentTrackInterface
    {
        $track = $this->createStub(ShipmentTrackInterface::class);
        $track->method('getTrackNumber')->willReturn($number);
        $track->method('getCarrierCode')->willReturn($carrierCode);

        return $track;
    }

    /**
     * @param array<int, string> $shipments the order's Magento shipments: increment ids by id
     */
    private function order(bool $canShip = true, array $shipments = []): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('canShip')->willReturn($canShip);
        $magentoShipments = [];
        foreach ($shipments as $id => $incrementId) {
            $magentoShipment = $this->createStub(MagentoShipmentModel::class);
            $magentoShipment->method('getId')->willReturn($id);
            $magentoShipment->method('getIncrementId')->willReturn($incrementId);
            $magentoShipments[] = $magentoShipment;
        }
        $order->method('getShipmentsCollection')->willReturn($magentoShipments);
        $order->method('getEntityId')->willReturn(42);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getShippingDescription')->willReturn('Kurier DPD');
        $order->method('getShippingMethod')->willReturn('polkurier_DPD');

        return $order;
    }

    private function carrierConfigs(): CarrierConfigProvider
    {
        /** @var CarrierConfig $inpost */
        $inpost = (new ObjectManager($this))->getObject(CarrierConfig::class);
        $inpost->setCarrierCode('INPOST')->setCustomLabel('Kurier InPost');
        // Only the settings are stubbed: the label is read from them as in the shop.
        $configs = $this->createPartialMock(CarrierConfigProvider::class, ['get']);
        $configs->method('get')->willReturnCallback(
            static fn(string $code): ?CarrierConfig => $code === 'INPOST' ? $inpost : null,
        );

        return $configs;
    }
}
