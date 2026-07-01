<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\Order\AddressInterface;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Shipping\Model\AbstractAddress;
use Polkurier\Shipping\Model\ResourceModel\Order\Address as AddressResource;

/**
 * @property AddressResource $_resource
 */
class Address extends AbstractAddress implements AddressInterface
{
    public const string FIELD_ORDER_ID = 'order_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_order_address';

    /**
     * @var string
     */
    protected $_eventObject = 'polkurier_order_address';

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
        $this->_init(AddressResource::class);
    }

    /**
     * @return int
     */
    public function getOrderId(): int
    {
        return $this->getData(static::FIELD_ORDER_ID);
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
     * @return string|null
     */
    public function getProvince(): ?string
    {
        return null;
    }

    /**
     * @param string|null $province
     *
     * @return void
     */
    public function setProvince(?string $province): void
    {
    }
}
