<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Sales\Model\Order;

/**
 * The size of one unit of each item an order ships, from the product's shipping dimensions. The
 * option chosen for a configurable product is the product that's shipped, so its size counts. A
 * bundle shipped together is measured as a whole; without a size of its own, its parts stacked
 * one on another stand in for it, which is never smaller than the parts packed well.
 */
readonly class ItemDimensions
{
    public const string LENGTH = 'polkurier_length';
    public const string WIDTH = 'polkurier_width';
    public const string HEIGHT = 'polkurier_height';

    public function __construct(
        private CollectionFactory $productCollectionFactory,
    ) {}

    /**
     * @param int[] $orderItemIds the items to measure
     * @return array<int, array{0: float, 1: float, 2: float}> length, width and height in centimetres
     *     by order item id, for the items whose size is known
     */
    public function get(Order $order, array $orderItemIds): array
    {
        $productIds = [];
        $parts = [];
        foreach ($order->getAllItems() as $item) {
            $id = (int)$item->getItemId();
            if (!\in_array($id, $orderItemIds, true)) {
                continue;
            }
            $children = $item->getChildrenItems();
            $together = $children !== [] && !$item->isShipSeparately();
            $shipped = $together && \count($children) === 1 ? \reset($children) : $item;
            $productIds[$id] = [(int)$shipped->getProductId(), (int)$item->getProductId()];
            if ($together && \count($children) > 1) {
                foreach ($children as $child) {
                    $perUnit = (float)$child->getQtyOrdered() / \max((float)$item->getQtyOrdered(), 1.0);
                    $parts[$id][(int)$child->getProductId()] = ($parts[$id][(int)$child->getProductId()] ?? 0.0) + $perUnit;
                }
            }
        }
        if ($productIds === []) {
            return [];
        }

        $sizes = [];
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect([self::LENGTH, self::WIDTH, self::HEIGHT])
            ->addIdFilter(\array_unique(\array_merge(
                ...\array_values($productIds),
                ...\array_map('array_keys', \array_values($parts)),
            )));
        foreach ($collection->getItems() as $product) {
            $size = [
                (float)$product->getData(self::LENGTH),
                (float)$product->getData(self::WIDTH),
                (float)$product->getData(self::HEIGHT),
            ];
            if (\min($size) > 0) {
                $sizes[(int)$product->getId()] = $size;
            }
        }

        $dimensions = [];
        foreach ($productIds as $orderItemId => [$shippedId, $ownId]) {
            // The option shipped, or, when it has no size, the product ordered.
            $size = $sizes[$shippedId] ?? $sizes[$ownId] ?? $this->stacked($parts[$orderItemId] ?? [], $sizes);
            if ($size !== null) {
                $dimensions[$orderItemId] = $size;
            }
        }

        return $dimensions;
    }

    /**
     * The box that holds a bundle's parts stacked, each lying flat: as long and as wide as the
     * largest part, as high as all of them together.
     *
     * @param array<int, float> $parts quantity in one bundle by product id
     * @param array<int, array{0: float, 1: float, 2: float}> $sizes by product id
     * @return array{0: float, 1: float, 2: float}|null null unless every part has a size
     */
    private function stacked(array $parts, array $sizes): ?array
    {
        if ($parts === []) {
            return null;
        }
        $box = [0.0, 0.0, 0.0];
        foreach ($parts as $productId => $qty) {
            if (!isset($sizes[$productId])) {
                return null;
            }
            $size = $sizes[$productId];
            \rsort($size);
            $box = [\max($box[0], $size[0]), \max($box[1], $size[1]), $box[2] + $size[2] * \ceil($qty)];
        }

        return $box;
    }
}
