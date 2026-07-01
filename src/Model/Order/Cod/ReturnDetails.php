<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Order\Cod;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\Order\Cod\ReturnDetailsInterface;
use Polkurier\Shipping\Api\Data\Order\CodInterface;
use Polkurier\Shipping\Model\ResourceModel\Order\Cod\ReturnDetails as ReturnDetailsResource;
use Polkurier\Webservice\Enum\CodReturnReasonEnum;

/**
 * @property ReturnDetailsResource $_resource
 */
class ReturnDetails extends AbstractModel implements ReturnDetailsInterface
{
    public const string FIELD_ITEM_ID = 'item_id';
    public const string FIELD_ORDER_ID = 'order_id';
    public const string FIELD_REASON = 'reason';
    public const string FIELD_DESCRIPTION = 'description';
    public const string FIELD_DOCUMENT_NUMBER = 'document_number';
    public const string FIELD_INVOICE_NUMBER = 'invoice_number';
    public const string FIELD_AMOUNT = 'amount';
    public const string FIELD_DATE = 'date';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_order_cod_return_details';

    /**
     * @var string
     */
    protected $_eventObject = 'polkurier_order_cod_return_details';

    /**
     * @var CodInterface|null
     */
    private ?CodInterface $parent = null;

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(ReturnDetailsResource::class);
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
        if ($this->parent?->getOrderId() !== $orderId) {
            $this->parent = null;
        }
    }

    /**
     * @return CodInterface
     */
    public function getParent(): CodInterface
    {
        if (!$this->parent) {
            $this->parent = $this->_resource->loadParent($this);
        }

        return $this->parent;
    }

    /**
     * @param CodInterface $parent
     *
     * @return void
     */
    public function setParent(CodInterface $parent): void
    {
        $this->parent = $parent;
        $this->setOrderId($parent->getOrderId());
    }

    /**
     * @return CodReturnReasonEnum
     */
    public function getReason(): CodReturnReasonEnum
    {
        return CodReturnReasonEnum::from($this->getData(static::FIELD_REASON));
    }

    /**
     * @param CodReturnReasonEnum $reason
     *
     * @return void
     */
    public function setReason(CodReturnReasonEnum $reason): void
    {
        $this->setData(static::FIELD_REASON, $reason->value);
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
     * @return string
     */
    public function getDocumentNumber(): string
    {
        return $this->getData(static::FIELD_DOCUMENT_NUMBER);
    }

    /**
     * @param string $documentNumber
     *
     * @return void
     */
    public function setDocumentNumber(string $documentNumber): void
    {
        $this->setData(static::FIELD_DOCUMENT_NUMBER, $documentNumber);
    }

    /**
     * @return string[]|null
     */
    public function getInvoiceNumbers(): ?array
    {
        return $this->getData(static::FIELD_INVOICE_NUMBER)
            ? \explode(',', $this->getData(static::FIELD_INVOICE_NUMBER))
            : null;
    }

    /**
     * @param string[]|null $invoiceNumbers
     *
     * @return void
     */
    public function setInvoiceNumbers(?array $invoiceNumbers): void
    {
        $this->setData(static::FIELD_INVOICE_NUMBER, $invoiceNumbers ? \implode(',', $invoiceNumbers) : null);
    }

    /**
     * @return \DateTimeInterface
     */
    public function getDate(): \DateTimeInterface
    {
        return $this->getData(static::FIELD_DATE);
    }

    /**
     * @param \DateTimeInterface $date
     *
     * @return void
     */
    public function setDate(\DateTimeInterface $date): void
    {
        $this->setData(static::FIELD_DATE, $date);
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
}
