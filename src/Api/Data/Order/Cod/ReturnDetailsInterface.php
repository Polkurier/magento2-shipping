<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data\Order\Cod;

use Polkurier\Shipping\Api\Data\Order\CodInterface;
use Polkurier\Webservice\Api\Data\Entity\Order\Cod\ReturnDetailsInterface as BaseReturnDetailsInterface;

interface ReturnDetailsInterface extends BaseReturnDetailsInterface
{
    /**
     * @return int|null
     */
    public function getItemId(): ?int;

    /**
     * @param int|null $itemId
     *
     * @return void
     */
    public function setItemId(?int $itemId): void;

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
     * @return CodInterface
     */
    public function getParent(): CodInterface;

    /**
     * @param CodInterface $parent
     *
     * @return void
     */
    public function setParent(CodInterface $parent): void;
}
