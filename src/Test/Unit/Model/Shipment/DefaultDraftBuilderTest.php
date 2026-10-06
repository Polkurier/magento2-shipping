<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Enum\ParcelWeightEnum;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Mapper\SenderMapper;
use Polkurier\Shipping\Model\Mapper\StreetParser;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\Shipment\AutoPacker;
use Polkurier\Shipping\Model\Shipment\DefaultDraftBuilder;
use Polkurier\Shipping\Model\Shipment\Draft\Item;
use Polkurier\Shipping\Model\Shipment\Draft\ItemFactory;
use Polkurier\Shipping\Model\Shipment\Draft\Pack;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Shipping\Model\Shipment\DraftFactory;
use Polkurier\Shipping\Model\Shipment\OrderAmounts;
use Psr\Log\NullLogger;

#[CoversClass(DefaultDraftBuilder::class)]
#[AllowMockObjectsWithoutExpectations]
class DefaultDraftBuilderTest extends TestCase
{
    private int $packings = 0;

    public function testANewShipmentStartsPackedAutomatically(): void
    {
        [$packs, $estimated] = $this->createPacks(true);

        self::assertSame(1, $this->packings);
        self::assertTrue($estimated);
        self::assertSame(3, $packs[0]->getAmount());
    }

    public function testWithAutomaticPackingOffANewShipmentStartsWithTheDefaultTemplate(): void
    {
        [$packs, $estimated] = $this->createPacks(false);

        self::assertSame(0, $this->packings, 'Packing is left to the button.');
        self::assertFalse($estimated);
        self::assertSame(12, $packs[0]->getTemplateId());
        self::assertSame(1, $packs[0]->getAmount());
        self::assertSame(0.8, $packs[0]->getWeight(), 'Two mugs of 0.4 kg.');
    }

    public function testTheDefaultParcelIsOfTheShipmentsKind(): void
    {
        // The default template is an envelope; the shipment goes as parcels.
        [$packs] = $this->createPacks(false, defaultPackType: 'ENV');

        self::assertSame(14, $packs[0]->getTemplateId(), 'The first parcel template, not the envelope.');
    }

    public function testTheDefaultParcelWeighsItsTemplatesWeightWhenSetTo(): void
    {
        [$packs] = $this->createPacks(false, ParcelWeightEnum::Template);

        self::assertSame(25.0, $packs[0]->getWeight());
    }

    public function testTheDefaultParcelOfProductsWithoutAWeightWeighsItsTemplatesWeight(): void
    {
        [$packs] = $this->createPacks(false, ParcelWeightEnum::Products, 0.0);

        self::assertSame(25.0, $packs[0]->getWeight());
    }

    /**
     * @return array{0: Pack[], 1: bool}
     */
    private function createPacks(bool $packNewShipments, ParcelWeightEnum $parcelWeight = ParcelWeightEnum::Products, float $mugWeight = 0.4, string $defaultPackType = 'ST'): array
    {
        $config = $this->createStub(Config::class);
        $config->method('isPackingNewShipments')->willReturn($packNewShipments);
        $config->method('getParcelWeight')->willReturn($parcelWeight);
        $amounts = $this->createStub(OrderAmounts::class);
        $amounts->method('getItems')->willReturn([5 => ['name' => 'Mug', 'weight' => $mugWeight]]);
        $autoPacker = $this->createStub(AutoPacker::class);
        $autoPacker->method('packByItems')->willReturnCallback(function (): array {
            $this->packings++;

            return ['packs' => [(new Pack())->setAmount(3)], 'unmeasured' => [], 'unweighed' => [], 'unweighed_own' => [], 'templates' => true];
        });
        $template = $this->createStub(PackTemplateInterface::class);
        $template->method('getId')->willReturn(12);
        $template->method('getPackType')->willReturn($defaultPackType);
        $template->method('getWeight')->willReturn(25.0);
        $box = $this->createStub(PackTemplateInterface::class);
        $box->method('getId')->willReturn(14);
        $box->method('getPackType')->willReturn('ST');
        $box->method('getWeight')->willReturn(1.0);
        $masterData = $this->createStub(MasterData::class);
        $masterData->method('getDefaultPackTemplate')->willReturn($template);
        $masterData->method('getPackTemplates')->willReturn([$template, $box]);
        $packs = $this->createStub(PackFactory::class);
        $packs->method('create')->willReturnCallback(static fn(): Pack => new Pack());

        $builder = new DefaultDraftBuilder(
            $this->createStub(DraftFactory::class),
            $packs,
            $config,
            $masterData,
            $this->createStub(OrderPointStore::class),
            $this->createStub(StreetParser::class),
            $this->createStub(CarrierCatalog::class),
            new CarrierFeatures(),
            $amounts,
            $this->createStub(ItemFactory::class),
            $autoPacker,
            new NullLogger(),
            $this->createStub(SenderMapper::class),
        );
        $order = $this->createStub(Order::class);
        $order->method('getStoreId')->willReturn(1);

        /** @var array{0: Pack[], 1: bool} */
        return (new \ReflectionMethod($builder, 'createPacks'))
            ->invoke($builder, $order, [(new Item())->setOrderItemId(5)->setQty(2.0)], 'box');
    }
}
