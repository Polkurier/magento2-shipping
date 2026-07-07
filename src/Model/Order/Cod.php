<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Order;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Polkurier\Shipping\Api\Data\Order\Cod\ReturnDetailsInterface;
use Polkurier\Shipping\Api\Data\Order\CodInterface;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Shipping\Model\ResourceModel\Order\Cod as CodResource;
use Polkurier\Shipping\Model\ResourceModel\Order\Cod\ReturnDetails\Collection;
use Polkurier\Shipping\Model\ResourceModel\Order\Cod\ReturnDetails\CollectionFactory;
use Polkurier\Webservice\Enum\CodReturnTypeEnum;
use Polkurier\Webservice\Enum\CodStatusEnum;
use Polkurier\Webservice\Enum\CodTypeEnum;

/**
 * @property CodResource $_resource
 */
class Cod extends AbstractModel implements CodInterface
{
    public const string FIELD_ORDER_ID = 'order_id';
    public const string FIELD_TYPE = 'type';
    public const string FIELD_STATUS = 'status';
    public const string FIELD_AMOUNT = 'amount';
    public const string FIELD_BANK_ACCOUNT_ID = 'bank_account_id';
    public const string FIELD_BANK_ACCOUNT = 'bank_account';
    public const string FIELD_RETURN_TYPE = 'return_type';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_order_cod';

    /**
     * @var string
     */
    protected $_eventObject = 'polkurier_order_cod';

    /**
     * @var OrderInterface|null
     */
    private ?OrderInterface $order = null;

    /**
     * @var iterable<ReturnDetailsInterface>|null
     */
    private ?iterable $returnDetails = null;

    /**
     * @param CollectionFactory $returnDetailsCollectionFactory
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
         * @var CollectionFactory
         */
        private readonly CollectionFactory $returnDetailsCollectionFactory,

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
        $this->_init(CodResource::class);
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
     * @return CodTypeEnum
     */
    public function getType(): CodTypeEnum
    {
        return CodTypeEnum::from($this->getData(static::FIELD_TYPE));
    }

    /**
     * @param CodTypeEnum $type
     *
     * @return void
     */
    public function setType(CodTypeEnum $type): void
    {
        $this->setData(static::FIELD_TYPE, $type->value);
    }

    /**
     * @return CodStatusEnum
     */
    public function getStatus(): CodStatusEnum
    {
        return CodStatusEnum::from($this->getData(static::FIELD_STATUS));
    }

    /**
     * @param CodStatusEnum $status
     *
     * @return void
     */
    public function setStatus(CodStatusEnum $status): void
    {
        $this->setData(static::FIELD_STATUS, $status->value);
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return (float)$this->getData(static::FIELD_AMOUNT);
    }

    /**
     * @param float $amount
     *
     * @return void
     */
    public function setAmount(float $amount): void
    {
        $this->setData(static::FIELD_AMOUNT, $amount);
    }

    /**
     * @return int|null
     */
    public function getBankAccountId(): ?int
    {
        return (int)$this->getData(static::FIELD_BANK_ACCOUNT_ID) ?: null;
    }

    /**
     * @param int|null $accountId
     *
     * @return void
     */
    public function setBankAccountId(?int $accountId): void
    {
        $this->setData(static::FIELD_BANK_ACCOUNT_ID, $accountId);
    }

    /**
     * @return string
     */
    public function getBankAccount(): string
    {
        return $this->getData(static::FIELD_BANK_ACCOUNT);
    }

    /**
     * @param string $bankAccount
     *
     * @return void
     */
    public function setBankAccount(string $bankAccount): void
    {
        $this->setData(static::FIELD_BANK_ACCOUNT, $bankAccount);
    }

    /**
     * @return CodReturnTypeEnum
     */
    public function getReturnType(): CodReturnTypeEnum
    {
        return CodReturnTypeEnum::from($this->getData(static::FIELD_RETURN_TYPE));
    }

    /**
     * @param CodReturnTypeEnum $returnType
     *
     * @return void
     */
    public function setReturnType(CodReturnTypeEnum $returnType): void
    {
        $this->setData(static::FIELD_RETURN_TYPE, $returnType->value);
    }

    /**
     * @return iterable<ReturnDetailsInterface>
     */
    public function getReturnDetails(): iterable
    {
        if (!$this->returnDetails) {
            $this->returnDetails = $this->loadReturnDetails();
        }

        return $this->returnDetails;
    }

    /**
     * @param iterable<ReturnDetailsInterface> $returnDetails
     *
     * @return void
     */
    public function setReturnDetails(iterable $returnDetails): void
    {
        $this->returnDetails = $returnDetails;
    }

    /**
     * @return Collection
     */
    private function loadReturnDetails(): Collection
    {
        return $this->returnDetailsCollectionFactory->create()
            ->addOrderFilter($this->getOrderId())
            ->sortFromLatest();
    }
}
