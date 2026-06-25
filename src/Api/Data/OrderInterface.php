<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

use Polkurier\Webservice\Api\Data\Entity\OrderInterface as BaseOrderInterface;

interface OrderInterface extends BaseOrderInterface
{
    /**
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * @param int|null $orderId
     *
     * @return void
     */
    public function setOrderId(?int $orderId): void;

    /**
     * @return int|null
     */
    public function getParentOrderId(): ?int;

    /**
     * @param int|null $parentOrderId
     *
     * @return void
     */
    public function setParentOrderId(?int $parentOrderId): void;

    /**
     * @return int|null
     */
    public function getParentShipmentId(): ?int;

    /**
     * @param int|null $parentShipmentId
     *
     * @return void
     */
    public function setParentShipmentId(?int $parentShipmentId): void;

    /**
     * @return \DateTimeInterface|null
     */
    public function getExtCreatedAt(): ?\DateTimeInterface;

    /**
     * @param \DateTimeInterface|null $date
     *
     * @return void
     */
    public function setExtCreatedAt(?\DateTimeInterface $date): void;

    /**
     * @return string
     */
    public function getCarrierCode(): string;

    /**
     * @param string $carrierCode
     *
     * @return void
     */
    public function setCarrierCode(string $carrierCode): void;

    /**
     * @return \DateTimeInterface|null
     */
    public function getPickupFrom(): ?\DateTimeInterface;

    /**
     * @param \DateTimeInterface|null $pickupFrom
     *
     * @return void
     */
    public function setPickupFrom(?\DateTimeInterface $pickupFrom): void;

    /**
     * @return \DateTimeInterface|null
     */
    public function getPickupTo(): ?\DateTimeInterface;

    /**
     * @param \DateTimeInterface|null $pickupTo
     *
     * @return void
     */
    public function setPickupTo(?\DateTimeInterface $pickupTo): void;

    /**
     * @return bool
     */
    public function isPickupNoCourierOrder(): bool;

    /**
     * @param bool $value
     *
     * @return void
     */
    public function setPickupNoCourierOrder(bool $value): void;
}
