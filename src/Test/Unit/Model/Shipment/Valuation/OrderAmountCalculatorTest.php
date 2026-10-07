<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Valuation;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\WeightUnitConverter;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;
use Polkurier\Shipping\Test\Unit\Support\MagentoMeasures;

#[CoversClass(OrderAmountCalculator::class)]
class OrderAmountCalculatorTest extends TestCase
{
    public function testCodLeftIsTheTotalLessWhatOtherOrdersCollect(): void
    {
        $amounts = $this->amounts([200.0, 50.5]);
        $order = $this->order(500.0, []);

        self::assertSame(250.5, $amounts->getCodCollected($order));
        self::assertSame(249.5, $amounts->getCodRemaining($order));
        self::assertSame(0.0, $this->amounts([600.0])->getCodRemaining($order));
    }

    public function testPartOfTheOrderIsWorthItsItems(): void
    {
        $order = $this->order(515.0, [
            // Two books at 150 with tax, 20 off in all; a lamp at 200.
            $this->item(1, qtyOrdered: 2, rowTotal: 300.0, discount: 20.0, qtyToShip: 2),
            $this->item(2, qtyOrdered: 1, rowTotal: 200.0, qtyToShip: 1),
            // The option of a configurable product ships with its parent.
            $this->item(3, qtyOrdered: 1, rowTotal: 0.0, qtyToShip: 0, dummy: true),
        ]);
        $amounts = $this->amounts([]);

        self::assertSame(140.0, $amounts->getShipmentValue($order, [1 => 1.0]));
        self::assertSame(340.0, $amounts->getShipmentValue($order, [1 => 1.0, 2 => 1.0, 3 => 1.0]));
        self::assertSame(
            515.0,
            $amounts->getShipmentValue($order, [1 => 2.0, 2 => 1.0]),
            'The whole order is worth its total, shipping included.',
        );
        self::assertSame(
            515.0,
            $amounts->getShipmentValue($order),
            'Nothing shipped yet: everything left is the whole order.',
        );
    }

    public function testCashOnDeliveryCollectsWhatThePartIsWorthAndTheRestWithTheLastPart(): void
    {
        $order = $this->order(515.0, [
            $this->item(1, qtyOrdered: 2, rowTotal: 300.0, discount: 20.0, qtyToShip: 2),
            $this->item(2, qtyOrdered: 1, rowTotal: 200.0, qtyToShip: 1),
        ]);

        self::assertSame(140.0, $this->amounts([])->getCodFor($order, [1 => 1.0]));
        self::assertSame(
            515.0,
            $this->amounts([])->getCodFor($order, [1 => 2.0, 2 => 1.0]),
            'Everything left: the shipping too.',
        );
        self::assertSame(515.0, $this->amounts([])->getCodFor($order));
        // Another Polkurier order collects 400 already: no part collects more than the 115 left.
        self::assertSame(115.0, $this->amounts([400.0])->getCodFor($order, [1 => 1.0]));
    }

    public function testTheValueComesWithWhatItIsMadeOf(): void
    {
        $order = $this->order(515.0, [
            $this->item(1, qtyOrdered: 2, rowTotal: 300.0, discount: 20.0, qtyToShip: 2),
            $this->item(2, qtyOrdered: 1, rowTotal: 200.0, qtyToShip: 1),
        ]);
        $amounts = $this->amounts([]);

        self::assertSame(
            [
                'products' => 140.0,
                'shipping' => 0.0,
                'order_total' => 515.0,
                'whole' => false,
                'rest' => false,
                'shipping_covered' => false,
                'with_shipping' => false,
                'value' => 140.0,
            ],
            $amounts->getShipmentWorth($order, [1 => 1.0]),
        );
        self::assertSame(
            [
                'products' => 480.0,
                'shipping' => 0.0,
                'order_total' => 515.0,
                'whole' => true,
                'rest' => true,
                'shipping_covered' => false,
                'with_shipping' => true,
                'value' => 515.0,
            ],
            $amounts->getShipmentWorth($order),
            'The whole order: its products, and the total that counts, shipping included.',
        );
    }

    public function testThePartTheShippingIsIncludedWithIsWorthItToo(): void
    {
        $order = $this->order(515.0, [
            $this->item(1, qtyOrdered: 2, rowTotal: 300.0, discount: 20.0, qtyToShip: 2),
            $this->item(2, qtyOrdered: 1, rowTotal: 200.0, qtyToShip: 1),
        ], shipping: 35.0);
        $amounts = $this->amounts([]);

        self::assertSame(175.0, $amounts->getShipmentValue($order, [1 => 1.0], true));
        self::assertSame(
            515.0,
            $amounts->getShipmentValue($order, null, false),
            'The whole order is insured for its total, shipping or not.',
        );
        self::assertSame(175.0, $amounts->getCodFor($order, [1 => 1.0], true));
        self::assertSame(140.0, $amounts->getCodFor($order, [1 => 1.0], false));
    }

    public function testTheOrderIsAlwaysCollectedInFull(): void
    {
        $order = $this->order(515.0, [
            $this->item(1, qtyOrdered: 2, rowTotal: 300.0, discount: 20.0, qtyToShip: 2),
            $this->item(2, qtyOrdered: 1, rowTotal: 200.0, qtyToShip: 1),
        ], shipping: 35.0);

        self::assertSame(
            515.0,
            $this->amounts([])->getCodFor($order, [1 => 2.0, 2 => 1.0], false),
            'The whole order collects its total, shipping or not.',
        );
        // One book went first, collecting 140: the part carrying the rest collects the 375 left.
        $rest = $this->order(515.0, [
            $this->item(1, qtyOrdered: 2, rowTotal: 300.0, discount: 20.0, qtyToShip: 1),
            $this->item(2, qtyOrdered: 1, rowTotal: 200.0, qtyToShip: 1),
        ], shipping: 35.0);
        self::assertSame(375.0, $this->amounts([140.0])->getCodFor($rest, [1 => 1.0, 2 => 1.0], false));
        self::assertSame(
            375.0,
            $this->amounts([140.0])->getShipmentValue($rest, [1 => 1.0, 2 => 1.0]),
            'Insured for the products and the shipping it collects.',
        );
        // The first part included the shipping: the rest goes without it, insured for its products.
        self::assertSame(340.0, $this->amounts([175.0], 'kgs', true)->getShipmentValue($rest, [1 => 1.0, 2 => 1.0]));
        self::assertSame(
            140.0,
            $this->amounts([175.0], 'kgs', true)->getShipmentValue($rest, [1 => 1.0], true),
            'Not included twice.',
        );
    }

    public function testShippingIsWhatTheCustomerWasChargedForIt(): void
    {
        self::assertSame(14.99, $this->amounts([])->getShippingCharged($this->order(100.0, [], shipping: 14.99)));
        self::assertSame(
            4.99,
            $this->amounts([])->getShippingCharged($this->order(100.0, [], shipping: 14.99, shippingDiscount: 10.0)),
            'Less the discount on shipping.',
        );
        // 1 zł = 0.25 €: 5 € of shipping are 20 zł.
        self::assertSame(
            20.0,
            $this->amounts([])->getShippingCharged($this->order(100.0, [], 'EUR', 'PLN', 0.25, shipping: 5.0)),
        );
    }

    public function testWhatIsLeftAfterAPartialShipment(): void
    {
        $order = $this->order(515.0, [
            $this->item(1, qtyOrdered: 2, rowTotal: 300.0, discount: 20.0, qtyToShip: 1),
            $this->item(2, qtyOrdered: 1, rowTotal: 200.0, qtyToShip: 0),
        ]);

        self::assertSame(140.0, $this->amounts([])->getShipmentValue($order));
    }

    public function testNothingToGoByFallsBackToTheTotal(): void
    {
        $order = $this->order(515.0, [
            $this->item(1, qtyOrdered: 2, rowTotal: 300.0, qtyToShip: 0),
        ]);

        self::assertSame(
            515.0,
            $this->amounts([])->getShipmentValue($order),
            'A replacement for an order already shipped.',
        );
    }

    public function testAnOrderInEurosOfAShopInZlotyIsConvertedAtItsRate(): void
    {
        // 1 zł = 0.25 €: the order's 100 € are 400 zł.
        $order = $this->order(100.0, [$this->item(1, qtyOrdered: 2, rowTotal: 60.0, qtyToShip: 2)], 'EUR', 'PLN', 0.25);
        $amounts = $this->amounts([50.0]);

        self::assertTrue($amounts->isPriced($order));
        self::assertSame(400.0, $amounts->getGrandTotal($order));
        self::assertSame(350.0, $amounts->getCodRemaining($order), 'What is collected is in złoty already.');
        self::assertSame(120.0, $amounts->getShipmentValue($order, [1 => 1.0]));
    }

    public function testAnOrderInZlotyOfAShopInEurosIsTakenAsItIs(): void
    {
        $order = $this->order(400.0, [], 'PLN', 'EUR', 4.0);

        self::assertSame(400.0, $this->amounts([])->getGrandTotal($order));
    }

    public function testAnOrderWithNoZlotyAnywhereIsNotPriced(): void
    {
        $order = $this->order(100.0, [$this->item(1, qtyOrdered: 1, rowTotal: 100.0, qtyToShip: 1)], 'EUR', 'EUR');
        $amounts = $this->amounts([]);

        self::assertFalse($amounts->isPriced($order));
        self::assertSame(0.0, $amounts->getCodRemaining($order), 'Nothing to collect is filled in.');
        self::assertSame(0.0, $amounts->getShipmentValue($order), 'Nor to insure.');
    }

    public function testItemsWeighInKilogramsWhateverTheStoresUnit(): void
    {
        $order = $this->order(100.0, [$this->item(1, qtyOrdered: 1, rowTotal: 100.0, qtyToShip: 1, weight: 2.0)]);

        self::assertSame(2.0, $this->amounts([])->getItems($order)[1]['weight']);
        self::assertSame(0.907, $this->amounts([], 'lbs')->getItems($order)[1]['weight'], '2 lb.');
    }

    public function testVirtualItemsAreNotShippedAndCancelledOnesNotCounted(): void
    {
        $order = $this->order(300.0, [
            $this->item(1, qtyOrdered: 3, rowTotal: 150.0, qtyToShip: 1, canceled: 2),
            $this->item(2, qtyOrdered: 1, rowTotal: 150.0, qtyToShip: 1, virtual: true),
        ]);

        $items = $this->amounts([])->getItems($order);

        self::assertSame([1], \array_keys($items), 'A download has nothing to ship.');
        self::assertSame(1.0, $items[1]['qty'], 'Three ordered, two cancelled.');
        self::assertSame(50.0, $items[1]['unit']);
    }

    /**
     * @param float[] $codAmounts of the order's Polkurier orders that collect
     */
    private function amounts(
        array $codAmounts,
        string $weightUnit = 'kgs',
        bool $shippingIncluded = false,
    ): OrderAmountCalculator {
        $shipments = \array_map(function (float $amount) use ($shippingIncluded): ShipmentInterface {
            $shipment = $this->createStub(ShipmentInterface::class);
            $shipment->method('getCodAmount')->willReturn($amount);
            $shipment->method('getDraft')->willReturn(['shipping_included' => $shippingIncluded]);

            return $shipment;
        }, $codAmounts);
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('forOrder')->willReturn($shipments);

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($weightUnit);

        return new OrderAmountCalculator(
            $finder,
            new WeightUnitConverter($scopeConfig, MagentoMeasures::carrierHelper()),
        );
    }

    /**
     * @param Item[] $items
     */
    private function order(
        float $grandTotal,
        array $items,
        string $currency = 'PLN',
        string $baseCurrency = 'PLN',
        float $rate = 1.0,
        float $shipping = 0.0,
        float $shippingDiscount = 0.0,
    ): Order {
        $order = $this->createStub(Order::class);
        $order->method('getShippingInclTax')->willReturn($shipping);
        $order->method('getShippingDiscountAmount')->willReturn($shippingDiscount);
        $order->method('getOrderCurrencyCode')->willReturn($currency);
        $order->method('getBaseCurrencyCode')->willReturn($baseCurrency);
        $order->method('getBaseToOrderRate')->willReturn($rate);
        $order->method('getEntityId')->willReturn(7);
        $order->method('getGrandTotal')->willReturn($grandTotal);
        $order->method('getAllItems')->willReturn($items);

        return $order;
    }

    private function item(
        int $id,
        float $qtyOrdered,
        float $rowTotal,
        float $qtyToShip,
        float $discount = 0.0,
        bool $dummy = false,
        float $weight = 0.0,
        float $canceled = 0.0,
        bool $virtual = false,
    ): Item {
        $item = $this->createStub(Item::class);
        $item->method('getItemId')->willReturn($id);
        $item->method('getQtyOrdered')->willReturn($qtyOrdered);
        $item->method('getQtyCanceled')->willReturn($canceled);
        $item->method('getWeight')->willReturn($weight);
        $item->method('getRowTotalInclTax')->willReturn($rowTotal);
        $item->method('getDiscountAmount')->willReturn($discount);
        $item->method('getQtyToShip')->willReturn($qtyToShip);
        $item->method('getIsVirtual')->willReturn($virtual);
        $item->method('isDummy')->willReturn($dummy);

        return $item;
    }
}
