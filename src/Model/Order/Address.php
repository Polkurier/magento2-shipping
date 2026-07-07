<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Order;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
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
    public const string FIELD_PARENT_ID = 'parent_id';
    public const string FIELD_PARENT_ADDRESS_ID = 'parent_address_id';

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
     * @var OrderAddressInterface|null
     */
    private ?OrderAddressInterface $parentAddress = null;

    /**
     * @param OrderAddressRepositoryInterface $orderAddressRepository
     * @param Context $context
     * @param Registry $registry
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     *
     * @throws LocalizedException
     */
    public function __construct(
        /**
         * @var OrderAddressRepositoryInterface
         */
        private readonly OrderAddressRepositoryInterface $orderAddressRepository,

        Context $context,
        Registry $registry,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

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
        if ($order->getOrderId()) {
            $this->setOrderId($order->getOrderId());
        }
    }

    /**
     * @return int|null
     */
    public function getParentId(): ?int
    {
        return (int)$this->getData(static::FIELD_PARENT_ID) ?: null;
    }

    /**
     * @param int|null $parentId
     *
     * @return void
     */
    public function setParentId(?int $parentId): void
    {
        $this->setData(static::FIELD_PARENT_ID, $parentId);
    }

    /**
     * @return int|null
     */
    public function getParentAddressId(): ?int
    {
        return (int)$this->getData(static::FIELD_PARENT_ADDRESS_ID) ?: null;
    }

    /**
     * @param int|null $parentAddressId
     *
     * @return void
     */
    public function setParentAddressId(?int $parentAddressId): void
    {
        $this->setData(static::FIELD_PARENT_ADDRESS_ID, $parentAddressId);
        if ((int)$this->parentAddress?->getId() !== $parentAddressId) {
            $this->parentAddress = null;
        }
    }

    /**
     * @return OrderAddressInterface|null
     */
    public function getParentAddress(): ?OrderAddressInterface
    {
        if ($this->parentAddress === null && $this->getParentAddressId()) {
            $this->parentAddress = $this->orderAddressRepository->get($this->getParentAddressId());
        }

        return $this->parentAddress;
    }

    /**
     * @param OrderAddressInterface|null $parentAddress
     *
     * @return void
     */
    public function setParentAddress(?OrderAddressInterface $parentAddress): void
    {
        $this->parentAddress = $parentAddress;
        $this->setParentAddressId((int)$parentAddress?->getId() ?: null);
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
