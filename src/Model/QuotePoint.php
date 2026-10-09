<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\QuotePointInterface;

/**
 * The pickup point chosen in checkout, held on the quote until the order is placed.
 */
class QuotePoint extends AbstractModel implements QuotePointInterface
{
    public function getQuoteId(): int
    {
        return (int)$this->getData(self::FIELD_QUOTE_ID);
    }

    public function setQuoteId(int $quoteId): static
    {
        return $this->setData(self::FIELD_QUOTE_ID, $quoteId);
    }

    public function getQuoteAddressId(): int
    {
        return (int)$this->getData(self::FIELD_QUOTE_ADDRESS_ID);
    }

    public function setQuoteAddressId(int $quoteAddressId): static
    {
        return $this->setData(self::FIELD_QUOTE_ADDRESS_ID, $quoteAddressId);
    }

    public function getMethodCode(): string
    {
        return (string)$this->getData(self::FIELD_METHOD_CODE);
    }

    public function setMethodCode(string $methodCode): static
    {
        return $this->setData(self::FIELD_METHOD_CODE, $methodCode);
    }

    public function getPointId(): string
    {
        return (string)$this->getData(self::FIELD_POINT_ID);
    }

    public function setPointId(string $pointId): static
    {
        return $this->setData(self::FIELD_POINT_ID, $pointId);
    }

    public function getPointLabel(): ?string
    {
        $value = $this->getData(self::FIELD_POINT_LABEL);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setPointLabel(?string $pointLabel): static
    {
        return $this->setData(self::FIELD_POINT_LABEL, $pointLabel);
    }

    public function getPointCod(): ?bool
    {
        $value = $this->getData(self::FIELD_POINT_COD);

        return $value === null || $value === '' ? null : (bool)$value;
    }

    public function setPointCod(?bool $pointCod): static
    {
        return $this->setData(self::FIELD_POINT_COD, $pointCod === null ? null : (int)$pointCod);
    }

    protected function _construct(): void
    {
        $this->_init(ResourceModel\QuotePoint::class);
    }
}
