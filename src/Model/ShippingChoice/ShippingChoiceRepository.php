<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ShippingChoice;

use Magento\Framework\App\ResourceConnection;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\Data\ShippingChoiceInterface;
use Polkurier\Shipping\Api\ShippingChoiceRepositoryInterface;
use Polkurier\Shipping\Model\ShippingChoiceFactory;

/**
 * The customer's shipping choices, in their own table of the sales database.
 */
readonly class ShippingChoiceRepository implements ShippingChoiceRepositoryInterface
{
    private const string TABLE = 'polkurier_order_shipping_choice';

    public function __construct(
        private ResourceConnection $resource,
        private ShippingChoiceFactory $shippingChoiceFactory,
    ) {}

    public function getForOrder(int $orderId): ?ShippingChoiceInterface
    {
        return $this->getForOrders([$orderId])[$orderId] ?? null;
    }

    /**
     * @param int[] $orderIds
     *
     * @return ShippingChoiceInterface[]
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
                ->where(ShippingChoiceInterface::FIELD_ORDER_ID . ' IN (?)', \array_values(\array_unique($orderIds))),
        );
        $choices = [];
        foreach ($rows as $row) {
            $orderId = (int)$row[ShippingChoiceInterface::FIELD_ORDER_ID];
            $choices[$orderId] = $this->shippingChoiceFactory->create(['data' => $row]);
        }

        return $choices;
    }

    public function keepForOrder(
        int $orderId,
        ?string $shippingMethod,
        ?string $shippingDescription,
        ?OrderPointInterface $point,
    ): void {
        $this->resource->getConnection('sales')->insertOnDuplicate(
            $this->resource->getTableName(self::TABLE, 'sales'),
            [
                ShippingChoiceInterface::FIELD_ORDER_ID => $orderId,
                ShippingChoiceInterface::FIELD_SHIPPING_METHOD => $shippingMethod,
                ShippingChoiceInterface::FIELD_SHIPPING_DESCRIPTION => $shippingDescription,
                ShippingChoiceInterface::FIELD_POINT_METHOD_CODE => $point?->getMethodCode(),
                ShippingChoiceInterface::FIELD_POINT_ID => $point?->getPointId(),
                ShippingChoiceInterface::FIELD_POINT_LABEL => $point?->getPointLabel(),
                ShippingChoiceInterface::FIELD_POINT_COD => $point?->getPointCod() === null
                    ? null
                    : (int)$point->getPointCod(),
            ],
            // A duplicate keeps the first one.
            [ShippingChoiceInterface::FIELD_ORDER_ID],
        );
    }

    public function deleteForOrder(int $orderId): void
    {
        $this->resource->getConnection('sales')->delete(
            $this->resource->getTableName(self::TABLE, 'sales'),
            [ShippingChoiceInterface::FIELD_ORDER_ID . ' = ?' => $orderId],
        );
    }
}
