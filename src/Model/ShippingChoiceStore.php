<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\App\ResourceConnection;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\Data\ShippingChoiceInterface;

/**
 * Keeps the shipping the customer chose, on the side, while the order shows another.
 */
readonly class ShippingChoiceStore
{
    private const string TABLE = 'polkurier_order_shipping_choice';

    public function __construct(
        private ResourceConnection $resource,
        private ShippingChoiceFactory $factory,
    ) {}

    public function get(int $orderId): ?ShippingChoiceInterface
    {
        return $this->getForOrders([$orderId])[$orderId] ?? null;
    }

    /**
     * @param int[] $orderIds
     *
     * @return array<int, ShippingChoiceInterface> by order id, for the orders that have one
     */
    public function getForOrders(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }
        $connection = $this->resource->getConnection('sales');
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE, 'sales'))
                ->where(ShippingChoiceInterface::ORDER_ID . ' IN (?)', \array_values(\array_unique($orderIds))),
        );
        $choices = [];
        foreach ($rows as $row) {
            $choices[(int)$row[ShippingChoiceInterface::ORDER_ID]] = $this->factory->create(['data' => $row]);
        }

        return $choices;
    }

    /**
     * Keeps the order's shipping as it is now, unless it's kept already: the customer's choice
     * is what the order had before it was first changed.
     */
    public function keep(int $orderId, ?string $shippingMethod, ?string $shippingDescription, ?OrderPointInterface $point): void
    {
        $this->resource->getConnection('sales')->insertOnDuplicate(
            $this->resource->getTableName(self::TABLE, 'sales'),
            [
                ShippingChoiceInterface::ORDER_ID => $orderId,
                ShippingChoiceInterface::SHIPPING_METHOD => $shippingMethod,
                ShippingChoiceInterface::SHIPPING_DESCRIPTION => $shippingDescription,
                ShippingChoiceInterface::POINT_METHOD_CODE => $point?->getMethodCode(),
                ShippingChoiceInterface::POINT_ID => $point?->getPointId(),
                ShippingChoiceInterface::POINT_LABEL => $point?->getPointLabel(),
                ShippingChoiceInterface::POINT_COD => $point !== null && $point->isPointCod() ? 1 : 0,
            ],
            // A duplicate keeps the first one.
            [ShippingChoiceInterface::ORDER_ID],
        );
    }

    public function delete(int $orderId): void
    {
        $this->resource->getConnection('sales')->delete(
            $this->resource->getTableName(self::TABLE, 'sales'),
            [ShippingChoiceInterface::ORDER_ID . ' = ?' => $orderId],
        );
    }
}
