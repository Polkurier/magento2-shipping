<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Native;

use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Shipment\DocumentService;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\Draft\DefaultDraftBuilder;
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\Draft\Item;
use Polkurier\Shipping\Model\Shipment\Draft\ItemFactory;
use Polkurier\Shipping\Model\Shipment\Draft\Pack;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Shipping\Model\Shipment\DraftFactory;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelPackageMapper;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\Placement\PlacementService;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Test\Unit\Support\MagentoMeasures;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;

#[CoversClass(NativeLabelService::class)]
#[CoversClass(NativeLabelPackageMapper::class)]
#[AllowMockObjectsWithoutExpectations]
class NativeLabelServiceTest extends TestCase
{
    private const string PDF = "%PDF-1.4\n%label";

    private PlacementService&MockObject $management;
    private DocumentService&MockObject $documentService;

    /**
     * The Polkurier order the Magento shipment already has, if any.
     */
    private ?ShipmentInterface $linked = null;

    private ?ShipmentDraftInterface $placed = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $options = null;

    /**
     * @var array<int, float>|null
     */
    private ?array $qtys = null;

    /**
     * Magento's "Email Copy of Shipment", as its Save puts it on the order.
     */
    private bool $emailCopy = false;

    public function testPackagesAreCheckedAgainstWhatTheShipmentCarries(): void
    {
        $shipment = $this->createMagentoShipment(null);
        $this->management->expects(self::never())->method('placeForNativeShipment');
        $item = [
            'params' => ['container' => 'ST', 'weight' => '1'],
            'items' => [11 => ['qty' => '2', 'order_item_id' => '5']],
        ];
        $native = $this->createMock(NativeShipmentService::class);
        $native->expects(self::once())->method('assertPacked')->with(
            self::anything(),
            self::callback(static fn(array $packs): bool => \count($packs) === 2
                && $packs[1]->getItems()[0]->getOrderItemId() === 5
                && $packs[1]->getItems()[0]->getQty() === 2.0),
            [5 => 2.0],
        )->willThrowException(new LocalizedException(__('Two mugs in each parcel, of the two the shipment carries.')));

        $this->expectException(LocalizedException::class);

        $this->createService($native)->request($this->createLabelRequest($shipment, [1 => $item, 2 => $item]));
    }

    public function testPackagesBecomeParcelsInKilogramsAndCentimetres(): void
    {
        $shipment = $this->createMagentoShipment(null);
        $set = [];
        $shipment->method('setData')->willReturnCallback(
            function (string $key, mixed $value) use (&$set, $shipment) {
                $set[$key] = $value;

                return $shipment;
            },
        );

        $answer = $this->createService()->request($this->createLabelRequest($shipment, [
            1 => [
                'params' => [
                    'container' => 'ST',
                    'weight' => '2.2',
                    'weight_units' => 'POUND',
                    'length' => '10',
                    'width' => '5',
                    'height' => '4',
                    'dimension_units' => 'INCH',
                ],
            ],
            2 => [
                'params' => [
                    'container' => 'NST',
                    'weight' => '3',
                    'weight_units' => 'KILOGRAM',
                    'length' => '30',
                    'width' => '20',
                    'height' => '10',
                    'dimension_units' => 'CENTIMETER',
                ],
            ],
        ]));

        self::assertSame(
            [NativeLabelService::FIELD_LINK => 7, NativeLabelService::FIELD_CARRIER => 'DPD'],
            $set,
            'Linked once saved; its tracks are titled by the carrier it went with.',
        );
        $packs = $this->getPlacedDraft()->getPacks();
        self::assertCount(2, $packs, 'One Polkurier order, a parcel per package.');
        self::assertSame(0.998, $packs[0]->getWeight());
        self::assertSame(
            [26, 13, 11],
            [$packs[0]->getLength(), $packs[0]->getWidth(), $packs[0]->getHeight()],
            'Rounded up to whole centimetres.',
        );
        self::assertSame('ST', $packs[0]->getPackType());
        self::assertSame('NST', $packs[1]->getPackType());
        self::assertSame(3.0, $packs[1]->getWeight());
        self::assertSame([5 => 2.0], $this->qtys, 'The defaults are worked out for the items in the shipment.');
        self::assertSame(
            [['tracking_number' => ['WB-1', 'WB-2'], 'label_content' => self::PDF]],
            $answer->getData('info'),
            'Magento tracks every waybill and stores the one label.',
        );
    }

    public function testOptionsFromTheShipmentPageOverrideTheDefaults(): void
    {
        $this->options = [
            'carrier_code' => 'INPOST',
            'cod_amount' => '',
            'services' => ['', 'SMS'],
            'recipient_person' => 'Someone Else',
        ];

        $this->createService()->request($this->createLabelRequest($this->createMagentoShipment(null), [
            1 => ['params' => ['container' => 'ST', 'weight' => '1']],
        ]));

        self::assertSame('INPOST', $this->getPlacedDraft()->getCarrierCode());
        self::assertNull($this->getPlacedDraft()->getCodAmount(), 'Cleared on the page.');
        self::assertSame(['SMS'], $this->getPlacedDraft()->getServices());
        self::assertSame(
            'Jan Kowalski',
            $this->getPlacedDraft()->getRecipientPerson(),
            'The recipient is the order\'s.',
        );
    }

    public function testANewShipmentKeepsMagentosEmailChoice(): void
    {
        $this->emailCopy = true;

        $this->createService()->request($this->createLabelRequest($this->createMagentoShipment(null), []));

        self::assertTrue(
            $this->getPlacedDraft()->shouldNotifyCustomer(),
            'For a Magento shipment made later, if it comes to it.',
        );
    }

    public function testALabelForAShipmentMadeBeforeEmailsNobody(): void
    {
        $this->emailCopy = true;

        $this->createService()->request($this->createLabelRequest($this->createMagentoShipment(31), []));

        self::assertFalse($this->getPlacedDraft()->shouldNotifyCustomer());
    }

    public function testAShipmentWithAPolkurierOrderGetsItsLabelWithoutPlacingAnother(): void
    {
        $this->linked = $this->createPolkurierShipment(['WB-9']);
        $this->management->expects(self::never())->method('placeForNativeShipment');

        $answer = $this->createService()->request($this->createLabelRequest($this->createMagentoShipment(31), []));

        self::assertSame([['tracking_number' => ['WB-9'], 'label_content' => self::PDF]], $answer->getData('info'));
    }

    public function testWithoutALabelTheWaybillsAreStillTracked(): void
    {
        $this->documentService = $this->createMock(DocumentService::class);
        $this->documentService->method('getLabels')->willThrowException(new LocalizedException(__('Not ready')));
        $shipment = $this->createMagentoShipment(null);
        $shipment->expects(self::exactly(2))->method('addTrack');
        $flags = [];
        $shipment->method('setData')->willReturnCallback(
            function (string $key, mixed $value) use (&$flags, $shipment) {
                $flags[$key] = $value;

                return $shipment;
            },
        );

        $label = $this->createService();
        $answer = $label->request($this->createLabelRequest($shipment, [
            1 => ['params' => ['container' => 'ST', 'weight' => '1']],
        ]));

        self::assertSame([], $answer->getData('info'));
        self::assertTrue($flags[NativeLabelService::FIELD_NO_LABEL] ?? false, 'No empty label is stored.');
        self::assertNotNull($label->takeUnreadyLabel(), 'For Magento\'s message about the shipment to say so.');
        self::assertNull($label->takeUnreadyLabel(), 'Said once.');
        self::assertSame(7, $flags[NativeLabelService::FIELD_LINK] ?? null, 'The order is linked all the same.');
    }

    public function testTheLabelOptionsCarryEveryFieldOfASenderTypedIn(): void
    {
        foreach (ShipmentDraftInterface::NEW_SENDER_FIELDS as $key) {
            self::assertContains($key, NativeLabelService::OPTION_FIELDS);
        }
    }

    protected function setUp(): void
    {
        $this->management = $this->createMock(PlacementService::class);
        $this->management->method('placeForNativeShipment')->willReturnCallback(
            function (int $orderId, ShipmentDraftInterface $draft): ShipmentInterface {
                $this->placed = $draft;

                return $this->createPolkurierShipment(['WB-1', 'WB-2']);
            },
        );
        $this->documentService = $this->createMock(DocumentService::class);
        $this->documentService->method('getLabels')->willReturn([
            'content' => self::PDF,
            'file_name' => 'x.pdf',
            'content_type' => 'application/pdf',
        ]);
    }

    private function getPlacedDraft(): ShipmentDraftInterface
    {
        self::assertNotNull($this->placed, 'A Polkurier order was placed.');

        return $this->placed;
    }

    /**
     * @param array<int, mixed> $packages
     */
    private function createLabelRequest(MagentoShipmentModel $shipment, array $packages): DataObject
    {
        return new DataObject(['order_shipment' => $shipment, 'packages' => $packages]);
    }

    private function createMagentoShipment(?int $id): MagentoShipmentModel&MockObject
    {
        $order = $this->createStub(Order::class);
        $order->method('getEntityId')->willReturn(42);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerNoteNotify')->willReturnCallback(fn(): bool => $this->emailCopy);
        $shipment = $this->createMock(MagentoShipmentModel::class);
        $shipment->method('getId')->willReturn($id);
        $shipment->method('getOrder')->willReturn($order);
        $shipment->method('getTracks')->willReturn([]);
        // As {@see \Polkurier\Shipping\Plugin\Adminhtml\NativeLabelPagePlugin} puts them there from the
        // request.
        $shipment->method('getData')->willReturnCallback(
            fn(string $key = '') => $key === NativeLabelService::FIELD_OPTIONS ? $this->options : null,
        );
        $item = $this->createStub(MagentoShipmentModel\Item::class);
        $item->method('getOrderItemId')->willReturn(5);
        $item->method('getQty')->willReturn(2.0);
        $shipment->method('getAllItems')->willReturn([$item]);

        return $shipment;
    }

    /**
     * @param string[] $waybills
     */
    private function createPolkurierShipment(array $waybills): ShipmentInterface
    {
        $shipment = $this->createStub(\Polkurier\Shipping\Model\Shipment::class);
        $shipment->method('getId')->willReturn(7);
        $shipment->method('getWaybills')->willReturn($waybills);
        $shipment->method('getCarrierCode')->willReturn('DPD');
        $shipment->method('getPolkurierNumber')->willReturn('POL7');

        return $shipment;
    }

    private function createService(?NativeShipmentService $nativeShipmentService = null): NativeLabelService
    {
        $draftFactory = $this->createStub(DraftFactory::class);
        $draftFactory->method('create')->willReturnCallback(static fn(): Draft => new Draft());
        $packFactory = $this->createStub(PackFactory::class);
        $packFactory->method('create')->willReturnCallback(static fn(): Pack => new Pack());

        $default = (new Draft())
            ->setCarrierCode('DPD')
            ->setShipmentType('box')
            ->setRecipientPerson('Jan Kowalski')
            ->setRecipientPhone('600700800')
            ->setCodAmount(99.0)
            ->setServices([])
            ->setPacks([(new Pack())->setPackType('ST')->setWeight(1.0)->setAmount(1)]);
        $builder = $this->createStub(DefaultDraftBuilder::class);
        $builder->method('createFromOrder')->willReturnCallback(
            function (Order $order, ?array $qtys) use ($default): Draft {
                $this->qtys = $qtys;

                return $default;
            },
        );

        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('findLatestLinkedTo')->willReturnCallback(fn(): ?ShipmentInterface => $this->linked);
        $tracks = $this->createStub(TrackFactory::class);
        $tracks->method('create')->willReturnCallback(fn(): Track => $this->createStub(Track::class));

        return new NativeLabelService(
            new NativeLabelPackageMapper(
                $packFactory,
                $this->createItemFactory(),
                MagentoMeasures::createCarrierHelper(),
            ),
            $this->management,
            $builder,
            new DraftHydrator($draftFactory, $packFactory, $this->createStub(ItemFactory::class)),
            $finder,
            $this->documentService,
            $nativeShipmentService ?? $this->createStub(NativeShipmentService::class),
            $tracks,
            $this->createStub(Logger::class),
            NewingObjectManager::createFactory(DataObjectFactory::class),
        );
    }

    private function createItemFactory(): ItemFactory
    {
        $factory = $this->createStub(ItemFactory::class);
        $factory->method('create')->willReturnCallback(static fn(): Item => new Item());

        return $factory;
    }
}
