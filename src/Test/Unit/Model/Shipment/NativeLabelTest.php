<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface as MagentoShipmentRepository;
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
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\DefaultDraftBuilder;
use Polkurier\Shipping\Model\Shipment\Documents;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\Draft\Item;
use Polkurier\Shipping\Model\Shipment\Draft\ItemFactory;
use Polkurier\Shipping\Model\Shipment\Draft\Pack;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Shipping\Model\Shipment\DraftFactory;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\MagentoShipment;
use Polkurier\Shipping\Model\Shipment\NativeLabel;
use Polkurier\Shipping\Model\Shipment\Placing;
use Psr\Log\NullLogger;

#[CoversClass(NativeLabel::class)]
#[AllowMockObjectsWithoutExpectations]
class NativeLabelTest extends TestCase
{
    private const string PDF = "%PDF-1.4\n%label";

    private Placing&MockObject $management;
    private Documents&MockObject $documents;
    private Collection&MockObject $linked;
    private ?ShipmentDraftInterface $placed = null;
    /**
     * @var array<string, mixed>|null
     */
    private ?array $options = null;
    /**
     * @var array<int, float>|null
     */
    private ?array $qtys = null;

    protected function setUp(): void
    {
        $this->management = $this->createMock(Placing::class);
        $this->management->method('forMagentoShipment')->willReturnCallback(
            function (int $orderId, ShipmentDraftInterface $draft): ShipmentInterface {
                $this->placed = $draft;

                return $this->polkurierShipment(['WB-1', 'WB-2']);
            },
        );
        $this->documents = $this->createMock(Documents::class);
        $this->documents->method('getLabels')->willReturn(['content' => self::PDF, 'file_name' => 'x.pdf', 'content_type' => 'application/pdf']);
        $this->linked = $this->createMock(Collection::class);
        $this->linked->method('addFieldToFilter')->willReturnSelf();
        $this->linked->method('setOrder')->willReturnSelf();
        $this->linked->method('setPageSize')->willReturnSelf();
        $this->linked->method('getFirstItem')->willReturn(new DataObject());
    }

    public function testPackagesCannotShipAnItemTwice(): void
    {
        $shipment = $this->magentoShipment(null);
        $this->management->expects(self::never())->method('forMagentoShipment');
        $item = ['params' => ['container' => 'ST', 'weight' => '1'], 'items' => [11 => ['qty' => '2', 'order_item_id' => '5']]];

        try {
            $this->label()->request($this->labelRequest($shipment, [1 => $item, 2 => $item]));
            self::fail('Two mugs in each package, of the two the shipment carries.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('more of an item than the shipment carries', $e->getMessage());
        }
    }

    public function testPackagesBecomeParcelsInKilogramsAndCentimetres(): void
    {
        $shipment = $this->magentoShipment(null);
        $set = [];
        $shipment->method('setData')->willReturnCallback(function (string $key, mixed $value) use (&$set, $shipment) {
            $set[$key] = $value;

            return $shipment;
        });

        $answer = $this->label()->request($this->labelRequest($shipment, [
            1 => ['params' => ['container' => 'ST', 'weight' => '2.2', 'weight_units' => 'POUND', 'length' => '10', 'width' => '5', 'height' => '4', 'dimension_units' => 'INCH']],
            2 => ['params' => ['container' => 'NST', 'weight' => '3', 'weight_units' => 'KILOGRAM', 'length' => '30', 'width' => '20', 'height' => '10', 'dimension_units' => 'CENTIMETER']],
        ]));

        self::assertSame(
            [NativeLabel::LINK => 7, NativeLabel::CARRIER => 'DPD'],
            $set,
            'Linked once saved; its tracks are titled by the carrier it went with.',
        );
        $packs = $this->placed()->getPacks();
        self::assertCount(2, $packs, 'One Polkurier order, a parcel per package.');
        self::assertSame(0.998, $packs[0]->getWeight());
        self::assertSame([26, 13, 11], [$packs[0]->getLength(), $packs[0]->getWidth(), $packs[0]->getHeight()], 'Rounded up to whole centimetres.');
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

        $this->label()->request($this->labelRequest($this->magentoShipment(null), [1 => ['params' => ['container' => 'ST', 'weight' => '1']]]));

        self::assertSame('INPOST', $this->placed()->getCarrierCode());
        self::assertNull($this->placed()->getCodAmount(), 'Cleared on the page.');
        self::assertSame(['SMS'], $this->placed()->getServices());
        self::assertSame('Jan Kowalski', $this->placed()->getRecipientPerson(), 'The recipient is the order\'s.');
    }

    public function testAShipmentWithAPolkurierOrderGetsItsLabelWithoutPlacingAnother(): void
    {
        $this->linked = $this->createMock(Collection::class);
        $this->linked->method('addFieldToFilter')->willReturnSelf();
        $this->linked->method('setOrder')->willReturnSelf();
        $this->linked->method('setPageSize')->willReturnSelf();
        $this->linked->method('getFirstItem')->willReturn($this->polkurierShipment(['WB-9']));
        $this->management->expects(self::never())->method('forMagentoShipment');

        $answer = $this->label()->request($this->labelRequest($this->magentoShipment(31), []));

        self::assertSame([['tracking_number' => ['WB-9'], 'label_content' => self::PDF]], $answer->getData('info'));
    }

    public function testWithoutALabelTheWaybillsAreStillTracked(): void
    {
        $this->documents = $this->createMock(Documents::class);
        $this->documents->method('getLabels')->willThrowException(new LocalizedException(\__('Not ready')));
        $shipment = $this->magentoShipment(null);
        $shipment->expects(self::exactly(2))->method('addTrack');
        $flags = [];
        $shipment->method('setData')->willReturnCallback(function (string $key, mixed $value) use (&$flags, $shipment) {
            $flags[$key] = $value;

            return $shipment;
        });

        $label = $this->label();
        $answer = $label->request($this->labelRequest($shipment, [1 => ['params' => ['container' => 'ST', 'weight' => '1']]]));

        self::assertSame([], $answer->getData('info'));
        self::assertTrue($flags[NativeLabel::NO_LABEL] ?? false, 'No empty label is stored.');
        self::assertNotNull($label->takeUnreadyLabel(), 'For Magento\'s message about the shipment to say so.');
        self::assertNull($label->takeUnreadyLabel(), 'Said once.');
        self::assertSame(7, $flags[NativeLabel::LINK] ?? null, 'The order is linked all the same.');
    }

    public function testOnlyMerchantsAllowedToShipCanPlaceOrders(): void
    {
        $this->management->expects(self::never())->method('forMagentoShipment');

        $this->expectException(LocalizedException::class);
        $this->label(allowed: false)->request($this->labelRequest($this->magentoShipment(null), []));
    }

    private function placed(): ShipmentDraftInterface
    {
        self::assertNotNull($this->placed, 'A Polkurier order was placed.');

        return $this->placed;
    }

    /**
     * @param array<int, mixed> $packages
     */
    private function labelRequest(MagentoShipmentModel $shipment, array $packages): DataObject
    {
        return new DataObject(['order_shipment' => $shipment, 'packages' => $packages]);
    }

    private function magentoShipment(?int $id): MagentoShipmentModel&MockObject
    {
        $order = $this->createStub(Order::class);
        $order->method('getEntityId')->willReturn(42);
        $order->method('getStoreId')->willReturn(1);
        $shipment = $this->createMock(MagentoShipmentModel::class);
        $shipment->method('getId')->willReturn($id);
        $shipment->method('getOrder')->willReturn($order);
        $shipment->method('getTracks')->willReturn([]);
        $item = $this->createStub(MagentoShipmentModel\Item::class);
        $item->method('getOrderItemId')->willReturn(5);
        $item->method('getQty')->willReturn(2.0);
        $shipment->method('getAllItems')->willReturn([$item]);

        return $shipment;
    }

    /**
     * @param string[] $waybills
     */
    private function polkurierShipment(array $waybills): ShipmentInterface
    {
        $shipment = $this->createStub(\Polkurier\Shipping\Model\Shipment::class);
        $shipment->method('getId')->willReturn(7);
        $shipment->method('getWaybills')->willReturn($waybills);
        $shipment->method('getCarrierCode')->willReturn('DPD');
        $shipment->method('getPolkurierNumber')->willReturn('POL7');

        return $shipment;
    }

    private function label(bool $allowed = true): NativeLabel
    {
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn($allowed);
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn(string $name) => $name === NativeLabel::OPTIONS_PARAM ? $this->options : null);

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
        $builder->method('createFromOrder')->willReturnCallback(function (Order $order, ?array $qtys) use ($default): Draft {
            $this->qtys = $qtys;

            return $default;
        });

        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturnCallback(fn(): Collection => $this->linked);
        $tracks = $this->createStub(TrackFactory::class);
        $tracks->method('create')->willReturnCallback(fn(): Track => $this->createStub(Track::class));

        return new NativeLabel(
            $this->createStub(Config::class),
            $authorization,
            $request,
            $this->createStub(OrderRepositoryInterface::class),
            $this->createStub(MagentoShipmentRepository::class),
            $this->management,
            $builder,
            new DraftHydrator($draftFactory, $packFactory, $this->createStub(ItemFactory::class)),
            $packFactory,
            new PackType(),
            $collections,
            $this->documents,
            $this->createStub(MagentoShipment::class),
            $tracks,
            new NullLogger(),
            $this->itemFactory(),
        );
    }

    private function itemFactory(): ItemFactory
    {
        $factory = $this->createStub(ItemFactory::class);
        $factory->method('create')->willReturnCallback(static fn(): Item => new Item());

        return $factory;
    }

    public function testTheLabelOptionsCarryEveryFieldOfASenderTypedIn(): void
    {
        foreach (ShipmentDraftInterface::NEW_SENDER_FIELDS as $key) {
            self::assertContains($key, NativeLabel::OPTION_FIELDS);
        }
    }
}
