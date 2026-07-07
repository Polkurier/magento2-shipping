<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data\Order;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Webservice\Api\Data\Entity\BaseAddressInterface;
use Polkurier\Webservice\Enum\AddressTypeEnum;

interface AddressInterface extends BaseAddressInterface
{
    public const string TYPE_COVER = 'COVER';

    /**
     * @return int|null
     */
    public function getAddressId(): ?int;

    /**
     * @param int|null $addressId
     *
     * @return void
     */
    public function setAddressId(?int $addressId): void;

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

    /**
     * @return int|null
     */
    public function getParentId(): ?int;

    /**
     * @param int|null $parentId
     *
     * @return void
     */
    public function setParentId(?int $parentId): void;

    /**
     * @return int|null
     */
    public function getParentAddressId(): ?int;

    /**
     * @param int|null $parentAddressId
     *
     * @return void
     */
    public function setParentAddressId(?int $parentAddressId): void;

    /**
     * @return OrderAddressInterface|null
     */
    public function getParentAddress(): ?OrderAddressInterface;

    /**
     * @param OrderAddressInterface|null $parentAddress
     *
     * @return void
     */
    public function setParentAddress(?OrderAddressInterface $parentAddress): void;

    /**
     * @return AddressTypeEnum
     */
    public function getType(): AddressTypeEnum;

    /**
     * @param AddressTypeEnum $type
     *
     * @return void
     */
    public function setType(AddressTypeEnum $type): void;
}
