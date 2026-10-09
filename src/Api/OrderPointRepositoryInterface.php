<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\Data\QuotePointInterface;

/**
 * Stores the pickup point an order goes to.
 *
 * @api
 */
interface OrderPointRepositoryInterface
{
    /**
     * @param int $orderId
     *
     * @return \Polkurier\Shipping\Api\Data\OrderPointInterface|null null when the order has none
     */
    public function getForOrder(int $orderId): ?OrderPointInterface;

    /**
     * The points of several orders, in one query.
     *
     * @param int[] $orderIds
     *
     * @return \Polkurier\Shipping\Api\Data\OrderPointInterface[] by order id, for the orders that
     *     have one
     */
    public function getForOrders(array $orderIds): array;

    /**
     * Sets the order's point, replacing the one it has.
     *
     * @param int $orderId
     * @param string $methodCode
     * @param string $pointId
     * @param string|null $pointLabel
     * @param bool|null $pointCod null when not known
     *
     * @return void
     */
    public function replaceForOrder(
        int $orderId,
        string $methodCode,
        string $pointId,
        ?string $pointLabel,
        ?bool $pointCod,
    ): void;

    /**
     * @param int $orderId
     *
     * @return void
     */
    public function deleteForOrder(int $orderId): void;

    /**
     * Gives the order the point chosen at checkout.
     *
     * @param \Polkurier\Shipping\Api\Data\QuotePointInterface $quotePoint
     * @param int $orderId
     *
     * @return void
     */
    public function copyFromQuote(QuotePointInterface $quotePoint, int $orderId): void;
}
