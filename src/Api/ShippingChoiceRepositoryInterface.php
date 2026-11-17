<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\Data\ShippingChoiceInterface;

/**
 * Keeps the shipping the customer chose, on the side, while the order shows another.
 *
 * @api
 */
interface ShippingChoiceRepositoryInterface
{
    /**
     * @param int $orderId
     *
     * @return \Polkurier\Shipping\Api\Data\ShippingChoiceInterface|null null when the order's
     *     shipping is the customer's own
     */
    public function getForOrder(int $orderId): ?ShippingChoiceInterface;

    /**
     * The choices of several orders, in one query.
     *
     * @param int[] $orderIds
     *
     * @return \Polkurier\Shipping\Api\Data\ShippingChoiceInterface[] by order id, for the orders
     *     that have one
     */
    public function getForOrders(array $orderIds): array;

    /**
     * Keeps the order's shipping as it is now, unless it's kept already: the customer's choice is
     * what the order had before it was first changed.
     *
     * @param int $orderId
     * @param string|null $shippingMethod
     * @param string|null $shippingDescription
     * @param \Polkurier\Shipping\Api\Data\OrderPointInterface|null $point
     *
     * @return void
     */
    public function keepForOrder(
        int $orderId,
        ?string $shippingMethod,
        ?string $shippingDescription,
        ?OrderPointInterface $point,
    ): void;

    /**
     * @param int $orderId
     *
     * @return void
     */
    public function deleteForOrder(int $orderId): void;
}
