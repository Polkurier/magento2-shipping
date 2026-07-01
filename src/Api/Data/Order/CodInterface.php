<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data\Order;

use Polkurier\Shipping\Api\Data\Order\Cod\ReturnDetailsInterface;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Webservice\Api\Data\Entity\Order\CodInterface as BaseCodInterface;

/**
 * @method iterable<ReturnDetailsInterface> getReturnDetails(void)
 * @method void setReturnDetails(iterable<ReturnDetailsInterface> $returnDetails)
 */
interface CodInterface extends BaseCodInterface
{
    /**
     * @return int
     */
    public function getOrderId(): int;

    /**
     * @param int $orderId
     *
     * @return void
     */
    public function setOrderId(int $orderId): void;

    /**
     * @return OrderInterface
     */
    public function getOrder(): OrderInterface;

    /**
     * @param OrderInterface $order
     *
     * @return void
     */
    public function setOrder(OrderInterface $order): void;
}
