<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

use Magento\Sales\Api\Data\OrderInterface as ParentOrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\Data\Order\AddressInterface as OrderAddressInterface;
use Polkurier\Shipping\Api\Data\Order\CodInterface;
use Polkurier\Shipping\Api\Data\Order\ItemInterface;
use Polkurier\Shipping\Api\Data\Order\PackInterface;
use Polkurier\Shipping\Api\Data\Order\WaybillInterface;
use Polkurier\Webservice\Api\Data\Entity\OrderInterface as BaseOrderInterface;

/**
 * @method OrderAddressInterface getRecipient(void)
 * @method void setRecipient(OrderAddressInterface $recipient)
 * @method OrderAddressInterface getSender(void)
 * @method void setSender(OrderAddressInterface $sender)
 * @method CodInterface|null getCod(void)
 * @method void setCod(CodInterface|null $cod)
 * @method iterable<ItemInterface> getItems(void)
 * @method void setItems(iterable<ItemInterface> $items)
 * @method iterable<PackInterface> getPacks(void)
 * @method void setPacks(iterable<PackInterface> $packs)
 * @method iterable<WaybillInterface> getWaybills(void)
 * @method void setWaybills(iterable<WaybillInterface> $waybills)
 */
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
     * @return ParentOrderInterface|null
     */
    public function getParentOrder(): ?ParentOrderInterface;

    /**
     * @param ParentOrderInterface|null $order
     *
     * @return void
     */
    public function setParentOrder(?ParentOrderInterface $order): void;

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
     * @return ShipmentInterface
     */
    public function getParentShipment(): ShipmentInterface;

    /**
     * @param ShipmentInterface $shipment
     *
     * @return void
     */
    public function setParentShipment(ShipmentInterface $shipment): void;

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
