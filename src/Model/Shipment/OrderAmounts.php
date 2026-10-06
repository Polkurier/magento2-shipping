<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\StoreWeightUnit;

/**
 * The amounts a shipment of an order starts from when the order ships in parts: how much cash on
 * delivery is still to be collected, and what the items shipped are worth and weigh.
 */
readonly class OrderAmounts
{
    public function __construct(
        private ShipmentFinder $finder,
        private StoreWeightUnit $weightUnit,
        private PlnAmounts $pln,
    ) {}

    /**
     * Whether the order's amounts can be given in złoty, as Polkurier takes them.
     */
    public function isPriced(Order $order): bool
    {
        return $this->pln->getRate($order) !== null;
    }

    /**
     * The order total in złoty; 0 when it can't be converted.
     */
    public function getGrandTotal(Order $order): float
    {
        return $this->pln->convert($order, (float)$order->getGrandTotal()) ?? 0.0;
    }

    /**
     * What the customer was charged for shipping and handling, with tax and after discounts, in
     * złoty; 0 when it can't be converted.
     */
    public function getShippingCharged(Order $order): float
    {
        $charged = \max(0.0, (float)$order->getShippingInclTax() - (float)$order->getShippingDiscountAmount());

        return \round($this->pln->convert($order, $charged) ?? 0.0, 2);
    }

    /**
     * Cash on delivery the order's Polkurier orders already collect.
     */
    public function getCodCollected(Order $order): float
    {
        $collected = 0.0;
        foreach ($this->finder->forOrder((int)$order->getEntityId(), ShipmentInterface::LIVE_STATES) as $shipment) {
            $collected += (float)$shipment->getCodAmount();
        }

        return \round($collected, 2);
    }

    /**
     * Whether one of the order's Polkurier orders already counts the shipping and handling in its
     * cash on delivery and insurance, so no other part includes it again.
     */
    public function isShippingCovered(Order $order): bool
    {
        foreach ($this->finder->forOrder((int)$order->getEntityId(), ShipmentInterface::LIVE_STATES) as $shipment) {
            if (!empty($shipment->getDraft()[ShipmentDraftInterface::SHIPPING_INCLUDED])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The order total less the cash on delivery its Polkurier orders already collect, in złoty; 0
     * when the order's currency can't be converted.
     */
    public function getCodRemaining(Order $order): float
    {
        return $this->isPriced($order) ? \max(0.0, \round($this->getGrandTotal($order) - $this->getCodCollected($order), 2)) : 0.0;
    }

    /**
     * What cash on delivery collects for a shipment, never more than is left to collect. The order
     * is collected in full: the whole order, or the part carrying all it still has to ship, collects
     * everything left, shipping and handling included. Another part collects its products, and the
     * shipping and handling too when it's included. Products without a price leave everything left.
     *
     * @param array<int, float>|null $qtys quantities shipped by order item id, or null for
     *     everything the order still has to ship
     * @param bool $withShipping whether part of the order includes the shipping and handling
     */
    public function getCodFor(Order $order, ?array $qtys = null, bool $withShipping = false): float
    {
        $remaining = $this->getCodRemaining($order);
        if ($qtys === null) {
            return $remaining;
        }
        $worth = $this->getShipmentWorth($order, $qtys, $withShipping);
        if ($worth['rest'] || $worth['products'] <= 0) {
            return $remaining;
        }

        return \min($remaining, \round($worth['products'] + ($worth['with_shipping'] ? $worth['shipping'] : 0.0), 2));
    }

    /**
     * The items that ship, by order item id: what one unit is worth in złoty (0 when the order's
     * currency can't be converted), with tax and after discounts, how many the order has, less those cancelled, how many are shipped and how many are left.
     * Items that ship as part of another (the options of a configurable product) are left out;
     * the item they belong to carries the price.
     *
     * @return array<int, array{name: string, sku: string, product_id: int, unit: float, weight: float, qty: float, qty_shipped: float, qty_to_ship: float}>
     */
    public function getItems(Order $order): array
    {
        $perKg = $this->weightUnit->kilogramsPerUnit($order->getStoreId());
        $rate = $this->pln->getRate($order) ?? 0.0;
        $items = [];
        foreach ($order->getAllItems() as $item) {
            $ordered = (float)$item->getQtyOrdered();
            if ($item->getIsVirtual() || $item->isDummy(true) || $ordered <= 0) {
                continue;
            }
            $items[(int)$item->getItemId()] = [
                // Shown as text: Magento keeps names as HTML, e.g. "Tee&trade;", which its pages render.
                'name' => \trim(\html_entity_decode((string)$item->getName(), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')),
                'sku' => (string)$item->getSku(),
                'product_id' => (int)$item->getProductId(),
                'unit' => \round(((float)$item->getRowTotalInclTax() - (float)$item->getDiscountAmount()) / $ordered * $rate, 4),
                // Of one unit, in kilograms; the order keeps it in the store's weight unit.
                'weight' => \round((float)$item->getWeight() * $perKg, 3),
                'qty' => \max(0.0, $ordered - (float)$item->getQtyCanceled()),
                'qty_shipped' => (float)$item->getQtyShipped(),
                'qty_to_ship' => (float)$item->getQtyToShip(),
            ];
        }

        return $items;
    }

    /**
     * What a shipment is worth in złoty: the order total when it carries the whole order (or
     * nothing could be matched to the order's items), otherwise what the items in it are worth; 0
     * when the order's currency can't be converted.
     *
     * @param array<int, float>|null $qtys quantities shipped by order item id, or null for
     *     everything the order still has to ship
     */
    public function getShipmentValue(Order $order, ?array $qtys = null, bool $withShipping = false): float
    {
        return $this->getShipmentWorth($order, $qtys, $withShipping)['value'];
    }

    /**
     * The shipment value, what it's insured for, with what it comes from, to show: what the
     * products in the shipment are worth (with tax, after discounts), the shipping and handling
     * charged, the order total, and whether the shipment carries the whole order. The whole order
     * is worth its total; part of it, its products, and the shipping and handling too when it's
     * included. All in złoty. Cash on delivery goes by
     * getCodFor(), which collects the order in full whatever this says.
     *
     * @param array<int, float>|null $qtys quantities shipped by order item id, or null for
     *     everything the order still has to ship
     * @param bool $withShipping whether part of the order includes the shipping and handling
     *
     * @return array{products: float, shipping: float, order_total: float, whole: bool, rest: bool, shipping_covered: bool, with_shipping: bool, value: float}
     *     rest: whether it carries all the order still has to ship; shipping_covered: whether
     *     another part includes the shipping and handling already
     */
    public function getShipmentWorth(Order $order, ?array $qtys = null, bool $withShipping = false): array
    {
        if ($qtys === null) {
            $qtys = [];
            foreach ($order->getAllItems() as $item) {
                $qtys[(int)$item->getItemId()] = (float)$item->getQtyToShip();
            }
        }
        $products = 0.0;
        $whole = true;
        $rest = true;
        foreach ($this->getItems($order) as $id => $item) {
            $qty = (float)($qtys[$id] ?? 0);
            $products += $item['unit'] * $qty;
            $whole = $whole && $qty >= $item['qty'];
            $rest = $rest && $qty >= $item['qty_to_ship'] - 0.0001;
        }
        $products = \round($products, 2);
        $total = $this->getGrandTotal($order);
        $shipping = $this->getShippingCharged($order);
        // The whole order includes it whatever was asked. A part includes it when asked, and the
        // part carrying the rest when no earlier part did, as it collects it then; never twice.
        $covered = !$whole && $this->isShippingCovered($order);
        $withShipping = $whole || (!$covered && ($withShipping || $rest));

        return [
            'products' => $products,
            'shipping' => $shipping,
            'order_total' => $total,
            'whole' => $whole,
            'rest' => $rest,
            'shipping_covered' => $covered,
            'with_shipping' => $withShipping,
            // Products without a price leave the order total to go by.
            // The whole order is insured for its total, shipping and handling included.
            'value' => match (true) {
                $whole || $products <= 0 => $total,
                $withShipping => \round($products + $shipping, 2),
                default => $products,
            },
        ];
    }
}
