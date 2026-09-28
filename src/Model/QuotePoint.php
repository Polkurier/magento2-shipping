<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * The pickup point chosen in checkout, held on the quote until the order is placed.
 */
class QuotePoint extends AbstractModel
{
    public const string QUOTE_ID = 'quote_id';
    public const string QUOTE_ADDRESS_ID = 'quote_address_id';
    public const string METHOD_CODE = 'method_code';
    public const string POINT_ID = 'point_id';
    public const string POINT_LABEL = 'point_label';
    public const string POINT_COD = 'point_cod';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\QuotePoint::class);
    }

    public function getQuoteId(): int
    {
        return (int)$this->getData(self::QUOTE_ID);
    }

    public function setQuoteId(int $quoteId): static
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    public function getQuoteAddressId(): int
    {
        return (int)$this->getData(self::QUOTE_ADDRESS_ID);
    }

    public function setQuoteAddressId(int $quoteAddressId): static
    {
        return $this->setData(self::QUOTE_ADDRESS_ID, $quoteAddressId);
    }

    public function getMethodCode(): string
    {
        return (string)$this->getData(self::METHOD_CODE);
    }

    public function setMethodCode(string $methodCode): static
    {
        return $this->setData(self::METHOD_CODE, $methodCode);
    }

    public function getPointId(): string
    {
        return (string)$this->getData(self::POINT_ID);
    }

    public function setPointId(string $pointId): static
    {
        return $this->setData(self::POINT_ID, $pointId);
    }

    public function getPointLabel(): ?string
    {
        $value = $this->getData(self::POINT_LABEL);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setPointLabel(?string $pointLabel): static
    {
        return $this->setData(self::POINT_LABEL, $pointLabel);
    }

    public function isPointCod(): bool
    {
        return (bool)$this->getData(self::POINT_COD);
    }

    public function setPointCod(bool $pointCod): static
    {
        return $this->setData(self::POINT_COD, (int)$pointCod);
    }
}
