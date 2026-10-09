<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Packing;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\Packing\ItemDimensionsProvider;

#[CoversClass(ItemDimensionsProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class ItemDimensionsProviderTest extends TestCase
{
    /**
     * @var array<int, array{0: float, 1: float, 2: float}> product sizes by product id
     */
    private array $sizes = [];

    public function testTheOptionChosenForAConfigurableProductIsMeasured(): void
    {
        $this->sizes = [10 => [50.0, 40.0, 30.0], 11 => [20.0, 15.0, 10.0]];
        $order = $this->order([$this->item(1, 10, 1.0, [$this->item(2, 11, 1.0)])]);

        self::assertSame([1 => [20.0, 15.0, 10.0]], $this->dimensions()->get($order, [1]));
    }

    public function testABundleWithASizeOfItsOwnIsMeasuredAsAWhole(): void
    {
        $this->sizes = [20 => [60.0, 40.0, 20.0], 21 => [10.0, 10.0, 10.0], 22 => [5.0, 5.0, 5.0]];
        $order = $this->order([$this->item(1, 20, 1.0, [$this->item(2, 21, 1.0), $this->item(3, 22, 1.0)])]);

        self::assertSame([1 => [60.0, 40.0, 20.0]], $this->dimensions()->get($order, [1]));
    }

    public function testABundleWithoutASizeStacksItsParts(): void
    {
        $this->sizes = [21 => [10.0, 30.0, 4.0], 22 => [20.0, 5.0, 3.0]];
        // Two bundles ordered: two of the first part and one of the second in each.
        $order = $this->order([$this->item(1, 20, 2.0, [$this->item(2, 21, 4.0), $this->item(3, 22, 2.0)])]);

        self::assertSame([1 => [30.0, 10.0, 11.0]], $this->dimensions()->get($order, [1]));
    }

    public function testABundleWithAPartWithoutASizeIsUnmeasured(): void
    {
        $this->sizes = [21 => [10.0, 10.0, 10.0]];
        $order = $this->order([$this->item(1, 20, 1.0, [$this->item(2, 21, 1.0), $this->item(3, 22, 1.0)])]);

        self::assertSame([], $this->dimensions()->get($order, [1]));
    }

    private function dimensions(): ItemDimensionsProvider
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addIdFilter')->willReturnSelf();
        $collection->method('getItems')->willReturnCallback(function (): array {
            $products = [];
            foreach ($this->sizes as $id => [$length, $width, $height]) {
                $product = $this->createStub(Product::class);
                $product->method('getId')->willReturn($id);
                $product->method('getData')->willReturnMap([
                    [ItemDimensionsProvider::FIELD_LENGTH, null, $length],
                    [ItemDimensionsProvider::FIELD_WIDTH, null, $width],
                    [ItemDimensionsProvider::FIELD_HEIGHT, null, $height],
                ]);
                $products[] = $product;
            }

            return $products;
        });
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new ItemDimensionsProvider($factory);
    }

    /**
     * @param Item[] $items
     */
    private function order(array $items): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getAllItems')->willReturn($items);

        return $order;
    }

    /**
     * @param Item[] $children
     */
    private function item(int $id, int $productId, float $qty, array $children = []): Item
    {
        $item = $this->createStub(Item::class);
        $item->method('getItemId')->willReturn($id);
        $item->method('getProductId')->willReturn($productId);
        $item->method('getQtyOrdered')->willReturn($qty);
        $item->method('getChildrenItems')->willReturn($children);
        $item->method('isShipSeparately')->willReturn(false);

        return $item;
    }
}
