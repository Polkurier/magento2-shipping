<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\Order\PackInterface;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Shipping\Model\AbstractPack;
use Polkurier\Shipping\Model\ResourceModel\Order\Pack as PackResource;

/**
 * @property PackResource $_resource
 */
class Pack extends AbstractPack implements PackInterface
{
    public const int DEFAULT_AMOUNT = 1;

    public const string FIELD_PACK_ID = 'pack_id';
    public const string FIELD_ORDER_ID = 'order_id';
    public const string FIELD_AMOUNT = 'amount';
    public const string FIELD_PRICE_NET = 'price_net';
    public const string FIELD_PRICE_GROSS = 'price_gross';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_order_pack';

    /**
     * @var string
     */
    protected $_eventObject = 'polkurier_order_pack';

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
        $this->_init(PackResource::class);
        $this->setAmount(static::DEFAULT_AMOUNT);
    }

    /**
     * @return int|null
     */
    public function getPackId(): ?int
    {
        return $this->getData(static::FIELD_PACK_ID);
    }

    /**
     * @param int|null $packId
     *
     * @return void
     */
    public function setPackId(?int $packId): void
    {
        $this->setData(static::FIELD_PACK_ID, $packId);
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
     * @return int
     */
    public function getAmount(): int
    {
        return $this->getData(static::FIELD_AMOUNT);
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
     * @return float|null
     */
    public function getPriceNet(): ?float
    {
        return $this->getData(static::FIELD_PRICE_NET);
    }

    /**
     * @param float|null $priceNet
     *
     * @return void
     */
    public function setPriceNet(?float $priceNet): void
    {
        $this->setData(static::FIELD_PRICE_NET, $priceNet);
    }

    /**
     * @return float|null
     */
    public function getPriceGross(): ?float
    {
        return $this->getData(static::FIELD_PRICE_GROSS);
    }

    /**
     * @param float|null $priceGross
     *
     * @return void
     */
    public function setPriceGross(?float $priceGross): void
    {
        $this->setData(static::FIELD_PRICE_GROSS, $priceGross);
    }
}
