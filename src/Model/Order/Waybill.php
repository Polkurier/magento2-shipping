<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\Order\WaybillInterface;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Shipping\Model\ResourceModel\Order\Waybill as WaybillResource;

/**
 * @property WaybillResource $_resource
 */
class Waybill extends AbstractModel implements WaybillInterface
{
    public const string FIELD_WAYBILL_ID = 'waybill_id';
    public const string FIELD_ORDER_ID = 'order_id';
    public const string FIELD_NUMBER = 'number';
    public const string FIELD_DEFAULT = 'default';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_order_waybill';

    /**
     * @var string
     */
    protected $_eventObject = 'polkurier_order_waybill';

    /**
     * @var OrderInterface|null
     */
    private ?OrderInterface $order = null;

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(WaybillResource::class);
    }

    /**
     * @return int|null
     */
    public function getWaybillId(): ?int
    {
        return $this->getData(static::FIELD_WAYBILL_ID);
    }

    /**
     * @param int|null $waybillId
     *
     * @return void
     */
    public function setWaybillId(?int $waybillId): void
    {
        $this->setData(static::FIELD_WAYBILL_ID, $waybillId);
    }

    /**
     * @return int
     */
    public function getOrderId(): int
    {
        return (int)$this->getData(static::FIELD_ORDER_ID);
    }

    /**
     * @param int $orderId
     *
     * @return void
     */
    public function setOrderId(int $orderId): void
    {
        $this->setData(static::FIELD_ORDER_ID, $orderId);
        if ($this->order?->getOrderId() !== $orderId) {
            $this->order = null;
        }
    }

    /**
     * @return OrderInterface
     */
    public function getOrder(): OrderInterface
    {
        if (!$this->order) {
            $this->order = $this->_resource->loadOrder($this);
        }

        return $this->order;
    }

    /**
     * @param OrderInterface $order
     *
     * @return void
     */
    public function setOrder(OrderInterface $order): void
    {
        $this->order = $order;
        $this->setOrderId($order->getOrderId());
    }

    /**
     * @return string
     */
    public function getNumber(): string
    {
        return $this->getData(static::FIELD_NUMBER);
    }

    /**
     * @param string $number
     *
     * @return void
     */
    public function setNumber(string $number): void
    {
        $this->setData(static::FIELD_NUMBER, $number);
    }

    /**
     * @return bool
     */
    public function isDefault(): bool
    {
        return (bool)$this->getData(static::FIELD_DEFAULT);
    }

    /**
     * @param bool $default
     *
     * @return void
     */
    public function setDefault(bool $default): void
    {
        $this->setData(static::FIELD_DEFAULT, $default);
    }
}
