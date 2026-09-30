<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;

/**
 * The amounts a shipment of an order starts from when the order ships in parts: how much cash on
 * delivery is still to be collected, and what the items shipped are worth and weigh.
 */
class OrderAmounts
{
    /**
     * Polkurier orders that collect their cash on delivery, or may: an unconfirmed one may exist.
     */
    private const array COLLECTING_STATES = [
        ShipmentInterface::STATE_PENDING,
        ShipmentInterface::STATE_CREATED,
        ShipmentInterface::STATE_UNKNOWN,
    ];

    private const float KG_PER_POUND = 0.45359237;

    public function __construct(
        private readonly CollectionFactory $shipmentCollectionFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly PlnAmounts $pln,
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
     * Cash on delivery the order's Polkurier orders already collect.
     */
    public function getCodCollected(Order $order): float
    {
        $collection = $this->shipmentCollectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::ORDER_ID, ['eq' => (int)$order->getEntityId()])
            ->addFieldToFilter(ShipmentInterface::STATE, ['in' => self::COLLECTING_STATES]);
        $collected = 0.0;
        foreach ($collection->getItems() as $shipment) {
            $collected += (float)$shipment->getCodAmount();
        }

        return \round($collected, 2);
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
     * The items that ship, by order item id: what one unit is worth in złoty (0 when the order's
     * currency can't be converted), with tax and after discounts, how many the order has, less those cancelled, how many are shipped and how many are left.
     * Items that ship as part of another (the options of a configurable product) are left out;
     * the item they belong to carries the price.
     *
     * @return array<int, array{name: string, sku: string, product_id: int, unit: float, weight: float, qty: float, qty_shipped: float, qty_to_ship: float}>
     */
    public function getItems(Order $order): array
    {
        $perKg = $this->scopeConfig->getValue(DirectoryHelper::XML_PATH_WEIGHT_UNIT, ScopeInterface::SCOPE_STORE, $order->getStoreId()) === 'lbs'
            ? self::KG_PER_POUND
            : 1.0;
        $rate = $this->pln->getRate($order) ?? 0.0;
        $items = [];
        foreach ($order->getAllItems() as $item) {
            $ordered = (float)$item->getQtyOrdered();
            if ($item->getIsVirtual() || $item->isDummy(true) || $ordered <= 0) {
                continue;
            }
            $items[(int)$item->getItemId()] = [
                'name' => (string)$item->getName(),
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
    public function getShipmentValue(Order $order, ?array $qtys = null): float
    {
        if ($qtys === null) {
            $qtys = [];
            foreach ($order->getAllItems() as $item) {
                $qtys[(int)$item->getItemId()] = (float)$item->getQtyToShip();
            }
        }
        $value = 0.0;
        $whole = true;
        foreach ($this->getItems($order) as $id => $item) {
            $qty = (float)($qtys[$id] ?? 0);
            $value += $item['unit'] * $qty;
            $whole = $whole && $qty >= $item['qty'];
        }

        return $whole || $value <= 0 ? $this->getGrandTotal($order) : \round($value, 2);
    }
}
