<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\Order\ItemInterface;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Shipping\Model\ResourceModel\Order\Item as ItemResource;
use Polkurier\Webservice\Enum\OrderItemTypeEnum;

/**
 * @property ItemResource $_resource
 */
class Item extends AbstractModel implements ItemInterface
{
    public const string FIELD_ITEM_ID = 'item_id';
    public const string FIELD_ORDER_ID = 'order_id';
    public const string FIELD_DESCRIPTION = 'description';
    public const string FIELD_AMOUNT = 'amount';
    public const string FIELD_PRICE = 'price';
    public const string FIELD_VALUE_NET = 'net_value';
    public const string FIELD_VALUE_GROSS = 'gross_value';
    public const string FIELD_TYPE = 'type';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_order_item';

    /**
     * @var string
     */
    protected $_eventObject = 'polkurier_order_item';

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
        $this->_init(ItemResource::class);
    }

    /**
     * @return int|null
     */
    public function getItemId(): ?int
    {
        return $this->getData(static::FIELD_ITEM_ID);
    }

    /**
     * @param int|null $itemId
     *
     * @return void
     */
    public function setItemId(?int $itemId): void
    {
        $this->setData(static::FIELD_ITEM_ID, $itemId);
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
    public function getDescription(): string
    {
        return $this->getData(static::FIELD_DESCRIPTION);
    }

    /**
     * @param string $description
     *
     * @return void
     */
    public function setDescription(string $description): void
    {
        $this->setData(static::FIELD_DESCRIPTION, $description);
    }

    /**
     * @return int
     */
    public function getAmount(): int
    {
        return (int)$this->getData(static::FIELD_AMOUNT);
    }

    /**
     * @param int $amount
     *
     * @return void
     */
    public function setAmount(int $amount): void
    {
        $this->setData(static::FIELD_AMOUNT, $amount);
    }

    /**
     * @return float
     */
    public function getPrice(): float
    {
        return (float)$this->getData(static::FIELD_PRICE);
    }

    /**
     * @param float $price
     *
     * @return void
     */
    public function setPrice(float $price): void
    {
        $this->setData(static::FIELD_PRICE, $price);
    }

    /**
     * @return float
     */
    public function getValueNet(): float
    {
        return (float)$this->getData(static::FIELD_VALUE_NET);
    }

    /**
     * @param float $valueNet
     *
     * @return void
     */
    public function setValueNet(float $valueNet): void
    {
        $this->setData(static::FIELD_VALUE_NET, $valueNet);
    }

    /**
     * @return float
     */
    public function getValueGross(): float
    {
        return (float)$this->getData(static::FIELD_VALUE_GROSS);
    }

    /**
     * @param float $valueGross
     *
     * @return void
     */
    public function setValueGross(float $valueGross): void
    {
        $this->setData(static::FIELD_VALUE_GROSS, $valueGross);
    }

    /**
     * @return OrderItemTypeEnum
     */
    public function getType(): OrderItemTypeEnum
    {
        return OrderItemTypeEnum::from($this->getData(static::FIELD_TYPE));
    }

    /**
     * @param OrderItemTypeEnum $type
     *
     * @return void
     */
    public function setType(OrderItemTypeEnum $type): void
    {
        $this->setData(static::FIELD_TYPE, $type->value);
    }
}
