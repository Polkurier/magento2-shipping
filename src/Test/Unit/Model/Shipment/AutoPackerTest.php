<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraft\ItemInterface;
use Polkurier\Shipping\Enum\ParcelWeightEnum;
use Polkurier\Shipping\Enum\WeightLimitEnum;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Shipping\Model\Shipment\AutoPacker;
use Polkurier\Shipping\Model\Shipment\Draft\Item;
use Polkurier\Shipping\Model\Shipment\Draft\ItemFactory;
use Polkurier\Shipping\Model\Shipment\Draft\Pack;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Shipping\Model\Shipment\ItemDimensions;
use Polkurier\Shipping\Model\Shipment\OrderAmounts;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;

#[CoversClass(AutoPacker::class)]
class AutoPackerTest extends TestCase
{
    /**
     * What the website's parcels go as.
     */
    private ShipmentTypeEnum $shipmentType = ShipmentTypeEnum::Box;

    private const array ITEMS = [
        5 => ['name' => 'Mug', 'sku' => 'MUG', 'product_id' => 1, 'unit' => 30.0, 'weight' => 0.4, 'qty' => 4.0, 'qty_shipped' => 0.0, 'qty_to_ship' => 4.0],
        6 => ['name' => 'Lamp', 'sku' => 'LAMP', 'product_id' => 2, 'unit' => 200.0, 'weight' => 2.5, 'qty' => 1.0, 'qty_shipped' => 0.0, 'qty_to_ship' => 1.0],
        7 => ['name' => 'Rug', 'sku' => 'RUG', 'product_id' => 3, 'unit' => 150.0, 'weight' => 3.0, 'qty' => 1.0, 'qty_shipped' => 0.0, 'qty_to_ship' => 1.0],
    ];

    /**
     * @var array<int, array{0: float, 1: float, 2: float}>
     */
    private array $sizes = [
        5 => [12.0, 10.0, 10.0],
        6 => [45.0, 25.0, 25.0],
        7 => [150.0, 20.0, 20.0],
    ];

    private float $margin = 0.0;
    private WeightLimitEnum $weightLimit = WeightLimitEnum::Warn;
    private ParcelWeightEnum $parcelWeight = ParcelWeightEnum::Products;

    /**
     * @var array<int, float> weights of one unit that differ from the items', by order item id
     */
    private array $weights = [];

    public function testTheMarginMakesEveryItemABitBigger(): void
    {
        $this->sizes = [5 => [10.0, 10.0, 10.0]];
        $templates = [$this->template(1, 'Cube', 20, 20, 20, 0.5), $this->template(2, 'Big cube', 40, 40, 40, 1.0)];
        $this->margin = 0.5;

        // 10.5 cm cubes: only one fits across 20 cm each way.
        $result = $this->packer($templates)->pack($this->order(), [5 => 2.0]);

        self::assertCount(1, $result['packs']);
        self::assertSame(2, $result['packs'][0]->getTemplateId(), 'Two no longer share the 20 cm cube.');
    }

    public function testSmallItemsShareTheSmallestParcelTheyFitIn(): void
    {
        $result = $this->packer()->pack($this->order(), [5 => 2.0]);

        self::assertCount(1, $result['packs']);
        $pack = $result['packs'][0];
        self::assertSame(1, $pack->getTemplateId(), 'The small box.');
        self::assertSame([[5, 2.0]], $this->contents($pack));
        self::assertSame(0.8, $pack->getWeight(), 'What the mugs weigh.');
    }

    public function testAnItemTooBigForTheSmallBoxGoesInABiggerOne(): void
    {
        // The lamp leaves 5 cm around it in the bigger box: plenty of volume, but no room for a mug.
        $result = $this->packer()->pack($this->order(), [5 => 1.0, 6 => 1.0]);

        self::assertCount(2, $result['packs']);
        self::assertSame(2, $result['packs'][0]->getTemplateId());
        self::assertSame([[6, 1.0]], $this->contents($result['packs'][0]));
        self::assertSame(2.5, $result['packs'][0]->getWeight());
        self::assertSame(1, $result['packs'][1]->getTemplateId(), 'The mug in the small box.');
        self::assertSame([[5, 1.0]], $this->contents($result['packs'][1]));
    }

    public function testRoomIsWhereTheGapsAreNotHowMuchVolumeIsLeft(): void
    {
        $this->sizes = [5 => [15.0, 10.0, 10.0], 6 => [45.0, 35.0, 25.0]];
        $templates = [$this->template(4, 'Box L', 50, 40, 30, 1.2)];

        // 20 625 cm³ stay empty around the big item, but only in 5 cm gaps.
        $result = $this->packer($templates)->pack($this->order(), [5 => 1.0, 6 => 1.0]);

        self::assertCount(2, $result['packs']);
        self::assertSame([[6, 1.0]], $this->contents($result['packs'][0]));
        self::assertSame([[5, 1.0]], $this->contents($result['packs'][1]));
    }

    public function testUnitsStandTheWayMoreOfThemFit(): void
    {
        // Four 12 × 10 × 10 mugs fit a 20 × 20 × 15 box standing up, two by two; lying, only two.
        $result = $this->packer([$this->template(1, 'Box S', 20, 20, 15, 0.5)])->pack($this->order(), [5 => 5.0]);

        self::assertCount(2, $result['packs']);
        self::assertSame([[5, 4.0]], $this->contents($result['packs'][0]));
        self::assertSame([[5, 1.0]], $this->contents($result['packs'][1]));
    }

    public function testOneBiggerParcelRatherThanTwo(): void
    {
        // The fifth mug doesn't fit in the small box with four: the medium one takes all five.
        $result = $this->packer()->pack($this->order(), [5 => 5.0]);

        self::assertCount(1, $result['packs']);
        self::assertSame(2, $result['packs'][0]->getTemplateId());
        self::assertSame([[5, 5.0]], $this->contents($result['packs'][0]));
    }

    public function testAsFewParcelsAsTheTemplatesAllow(): void
    {
        // Parcel by parcel, the small box would be filled first; one medium box takes it all.
        $this->sizes = [5 => [12.0, 10.0, 10.0], 6 => [30.0, 25.0, 20.0]];
        $result = $this->packer()->pack($this->order(), [5 => 6.0, 6 => 1.0], 'box');

        self::assertCount(1, $result['packs']);
        self::assertSame(2, $result['packs'][0]->getTemplateId());
    }

    public function testPalletsAreLeftOutOfABoxShipment(): void
    {
        $templates = [
            $this->template(1, 'Box S', 20, 20, 15, 0.5),
            $this->template(9, 'Pallet', 120, 80, 150, 25.0, 'pallet'),
        ];

        $result = $this->packer($templates)->pack($this->order(), [5 => 5.0], 'box');

        self::assertCount(2, $result['packs'], 'Two boxes rather than one pallet.');
        self::assertSame([1, 1], [$result['packs'][0]->getTemplateId(), $result['packs'][1]->getTemplateId()]);
    }

    public function testWithoutParcelsTheItemsGoAsTheWebsitesParcelsGo(): void
    {
        $templates = [
            $this->template(1, 'Box S', 20, 20, 15, 0.5),
            $this->template(9, 'Pallet', 120, 80, 150, 25.0, 'palette'),
            $this->template(3, 'Envelope', 30, 20, 2, 0.1, 'envelope'),
        ];

        $mugs = $this->packer($templates)->packByItems($this->order(), [5 => 2.0]);
        self::assertSame([1], \array_map(static fn(Pack $pack): ?int => $pack->getTemplateId(), $mugs['packs']), 'Parcels by default: a box, not an envelope or the pallet.');

        $rug = $this->packer($templates)->packByItems($this->order(), [7 => 1.0, 5 => 2.0]);
        self::assertSame([1, null], \array_map(static fn(Pack $pack): ?int => $pack->getTemplateId(), $rug['packs']), 'Too long for a box: a parcel of its own size, not a pallet for the order.');
        self::assertSame(['ST', 'ST'], \array_map(static fn(Pack $pack): string => $pack->getPackType(), $rug['packs']));
    }

    public function testAWebsiteShippingPalletsPacksOnlyPallets(): void
    {
        $this->shipmentType = ShipmentTypeEnum::Palette;
        $templates = [
            $this->template(1, 'Box S', 20, 20, 15, 0.5),
            $this->template(9, 'Pallet', 120, 80, 150, 25.0, 'palette'),
        ];

        $mugs = $this->packer($templates)->packByItems($this->order(), [5 => 2.0]);

        self::assertSame([9], \array_map(static fn(Pack $pack): ?int => $pack->getTemplateId(), $mugs['packs']), 'On the pallet, not in a box.');
    }

    public function testWhatNoEnvelopeTakesGoesAsAnEnvelopeOfItsOwnSize(): void
    {
        $this->shipmentType = ShipmentTypeEnum::Envelope;

        $result = $this->packer([$this->template(3, 'Envelope', 30, 20, 2, 0.1, 'envelope')])->packByItems($this->order(), [7 => 1.0]);

        self::assertCount(1, $result['packs']);
        self::assertNull($result['packs'][0]->getTemplateId());
        self::assertSame('ENV', $result['packs'][0]->getPackType(), 'The shipment stays of envelopes.');
    }

    public function testTheKindGivenGoesBeforeTheWebsites(): void
    {
        $templates = [
            $this->template(1, 'Box S', 20, 20, 15, 0.5),
            $this->template(9, 'Pallet', 120, 80, 150, 25.0, 'palette'),
        ];

        $mugs = $this->packer($templates)->packByItems($this->order(), [5 => 2.0], 'palette');

        self::assertSame([9], \array_map(static fn(Pack $pack): ?int => $pack->getTemplateId(), $mugs['packs']), 'What the carrier takes, say.');
    }

    public function testEachParcelGetsTheSmallestBoxForWhatItHolds(): void
    {
        // The rug fits no box, the lamp leaves no room for the mugs beside it, the mugs share one.
        $result = $this->packer()->pack($this->order(), [5 => 2.0, 6 => 1.0, 7 => 1.0], 'box');

        $parcels = \array_map(fn(Pack $pack): array => [$pack->getTemplateId(), $this->contents($pack)], $result['packs']);
        \usort($parcels, static fn(array $a, array $b): int => $a[1][0][0] <=> $b[1][0][0]);
        self::assertSame([
            [1, [[5, 2.0]]],
            [2, [[6, 1.0]]],
            [null, [[7, 1.0]]],
        ], $parcels);
    }

    public function testParcelsNeverHoldMoreThanFits(): void
    {
        $this->sizes = [5 => [10.0, 10.0, 10.0]];
        $templates = [$this->template(1, 'Cube', 20, 20, 20, 0.5)];

        $result = $this->packer($templates)->pack($this->order(), [5 => 9.0]);

        self::assertSame([[5, 8.0]], $this->contents($result['packs'][0]), 'Eight cubes fill the box.');
        self::assertSame([[5, 1.0]], $this->contents($result['packs'][1]));
    }

    public function testAnItemThatFitsNoTemplateGetsAParcelOfItsOwnSize(): void
    {
        $result = $this->packer()->pack($this->order(), [7 => 1.0]);

        $pack = $result['packs'][0];
        self::assertNull($pack->getTemplateId());
        self::assertSame([150, 20, 20], [$pack->getLength(), $pack->getWidth(), $pack->getHeight()]);
        self::assertSame(3.0, $pack->getWeight());
    }

    public function testByDefaultAWeightLimitDoesNotChangeThePacking(): void
    {
        // Four mugs weigh 1.6 kg, over the small box's 1 kg: they're packed together all the same.
        $templates = [$this->template(1, 'Box S', 20, 20, 15, 1.0)];

        $result = $this->packer($templates)->pack($this->order(), [5 => 4.0]);

        self::assertCount(1, $result['packs']);
        self::assertSame([[5, 4.0]], $this->contents($result['packs'][0]));
    }

    public function testKeptUnderTheLimitABoxHoldsNoMoreThanItShould(): void
    {
        $this->weightLimit = WeightLimitEnum::KeepUnder;
        $templates = [$this->template(1, 'Box S', 20, 20, 15, 1.0)];

        $result = $this->packer($templates)->pack($this->order(), [5 => 4.0]);

        self::assertSame([[[5, 2.0]], [[5, 2.0]]], \array_map(fn(Pack $pack): array => $this->contents($pack), $result['packs']));
    }

    public function testKeptUnderTheLimitABiggerBoxTakesWhatTheSmallOneShouldNot(): void
    {
        $this->weightLimit = WeightLimitEnum::KeepUnder;
        $templates = [$this->template(1, 'Box S', 20, 20, 15, 1.0), $this->template(2, 'Box M', 50, 30, 30, 5.0)];

        $result = $this->packer($templates)->pack($this->order(), [5 => 3.0]);

        self::assertCount(1, $result['packs']);
        self::assertSame(2, $result['packs'][0]->getTemplateId(), '1.2 kg of mugs: too heavy for the small box.');
    }

    public function testAnItemTooHeavyForEveryBoxStillGetsTheBoxItFits(): void
    {
        $this->weightLimit = WeightLimitEnum::KeepUnder;
        $templates = [$this->template(2, 'Box M', 50, 30, 30, 2.0)];

        $result = $this->packer($templates)->pack($this->order(), [6 => 1.0]);

        self::assertSame(2, $result['packs'][0]->getTemplateId(), 'The 2.5 kg lamp is over the limit, and pointed out on the screen.');
    }

    public function testSetToTheTemplatesWeightAParcelWeighsThat(): void
    {
        $this->parcelWeight = ParcelWeightEnum::Template;
        $templates = [$this->template(1, 'Box S', 20, 20, 15, 5.0)];

        $result = $this->packer($templates)->pack($this->order(), [5 => 2.0]);

        self::assertSame(5.0, $result['packs'][0]->getWeight(), 'Not the 0.8 kg of mugs.');
    }

    public function testAParcelWeighedByWhatItHoldsWeighsItsPackagingToo(): void
    {
        $templates = [$this->template(1, 'Box S', 20, 20, 15, 5.0, 'box', 0.35)];

        $result = $this->packer($templates)->pack($this->order(), [5 => 2.0]);

        self::assertSame(1.15, $result['packs'][0]->getWeight(), 'The 0.8 kg of mugs and the 0.35 kg box.');
    }

    public function testAParcelWithAProductWithoutAWeightWeighsItsTemplatesWeight(): void
    {
        $this->weights = [5 => 0.0];
        $templates = [$this->template(2, 'Box L', 100, 60, 60, 10.0)];

        $result = $this->packer($templates)->pack($this->order(), [5 => 1.0, 6 => 1.0]);

        self::assertCount(1, $result['packs']);
        self::assertSame(10.0, $result['packs'][0]->getWeight(), 'Not just the lamp\'s 2.5 kg.');
        self::assertSame(['Mug'], $result['unweighed']);
    }

    public function testAProductWithoutAWeightInAParcelOfItsOwnSizeIsTold(): void
    {
        // The rug fits no box: its parcel has no template whose weight it could take.
        $this->weights = [7 => 0.0];

        $result = $this->packer([$this->template(1, 'Box S', 20, 20, 15, 0.5)])->pack($this->order(), [5 => 1.0, 7 => 1.0], 'box');

        self::assertSame(['Rug'], $result['unweighed_own']);
    }

    public function testProductsWithoutAWeightAreReported(): void
    {
        $this->weights = [5 => 0.0];

        $result = $this->packer()->pack($this->order(), [5 => 1.0, 6 => 1.0]);

        self::assertSame(['Mug'], $result['unweighed']);
        self::assertNotSame([], $result['packs'], 'They are packed all the same.');
    }

    public function testWithoutDimensionsNothingIsPacked(): void
    {
        unset($this->sizes[6]);

        $result = $this->packer()->pack($this->order(), [5 => 1.0, 6 => 1.0]);

        self::assertSame([], $result['packs']);
        self::assertSame(['Lamp'], $result['unmeasured']);
    }

    /**
     * @return array<int, array{0: int, 1: float}>
     */
    private function contents(Pack $pack): array
    {
        return \array_map(static fn(ItemInterface $item): array => [$item->getOrderItemId(), $item->getQty()], $pack->getItems());
    }

    /**
     * @param PackTemplateInterface[]|null $templates
     */
    private function packer(?array $templates = null): AutoPacker
    {
        $amounts = $this->createStub(OrderAmounts::class);
        $items = self::ITEMS;
        foreach ($this->weights as $id => $weight) {
            $items[$id]['weight'] = $weight;
        }
        $amounts->method('getItems')->willReturn($items);
        $dimensions = $this->createStub(ItemDimensions::class);
        $dimensions->method('get')->willReturnCallback(fn(Order $order, array $ids): array => \array_intersect_key($this->sizes, \array_flip($ids)));
        $masterData = $this->createStub(MasterData::class);
        $masterData->method('getPackTemplates')->willReturn($templates ?? [
            $this->template(2, 'Box M', 50, 30, 30, 1.0),
            $this->template(1, 'Box S', 20, 20, 15, 0.5),
            $this->template(3, 'Envelope', 30, 20, 0, 0.1),
        ]);
        $packs = $this->createStub(PackFactory::class);
        $packs->method('create')->willReturnCallback(static fn(): Pack => new Pack());
        $items = $this->createStub(ItemFactory::class);
        $items->method('create')->willReturnCallback(static fn(): Item => new Item());

        $config = $this->createStub(Config::class);
        $config->method('getPackingMargin')->willReturnCallback(fn(): float => $this->margin);
        $config->method('getWeightLimit')->willReturnCallback(fn(): WeightLimitEnum => $this->weightLimit);
        $config->method('getParcelWeight')->willReturnCallback(fn(): ParcelWeightEnum => $this->parcelWeight);
        $config->method('getDefaultShipmentType')->willReturnCallback(fn(): ShipmentTypeEnum => $this->shipmentType);

        return new AutoPacker($amounts, $dimensions, $masterData, $packs, $items, $config);
    }

    private function template(int $id, string $name, int $length, int $width, int $height, float $weight, string $shipmentType = 'box', ?float $tare = null): PackTemplateInterface
    {
        $template = $this->createStub(PackTemplateInterface::class);
        $template->method('getTare')->willReturn($tare);
        $template->method('getShipmentType')->willReturn($shipmentType);
        $template->method('getId')->willReturn($id);
        $template->method('getName')->willReturn($name);
        $template->method('getPackType')->willReturn('ST');
        $template->method('getLength')->willReturn($length);
        $template->method('getWidth')->willReturn($width);
        $template->method('getHeight')->willReturn($height);
        $template->method('getWeight')->willReturn($weight);

        return $template;
    }

    private function order(): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getStoreId')->willReturn(1);

        return $order;
    }
}
