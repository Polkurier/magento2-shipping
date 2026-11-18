<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\DataObject;
use Polkurier\Shipping\Api\Data\ShippingChoiceInterface;

/**
 * What the customer chose for an order at checkout: the shipping method and the pickup point.
 */
class ShippingChoice extends DataObject implements ShippingChoiceInterface
{
    public function getOrderId(): int
    {
        return (int)$this->getData(self::FIELD_ORDER_ID);
    }

    public function getShippingMethod(): ?string
    {
        return $this->getNullable(self::FIELD_SHIPPING_METHOD);
    }

    public function getShippingDescription(): ?string
    {
        return $this->getNullable(self::FIELD_SHIPPING_DESCRIPTION);
    }

    public function getPointMethodCode(): ?string
    {
        return $this->getNullable(self::FIELD_POINT_METHOD_CODE);
    }

    public function getPointId(): ?string
    {
        return $this->getNullable(self::FIELD_POINT_ID);
    }

    public function getPointLabel(): ?string
    {
        return $this->getNullable(self::FIELD_POINT_LABEL);
    }

    public function getPointCod(): ?bool
    {
        $value = $this->getData(self::FIELD_POINT_COD);

        return $value === null || $value === '' ? null : (bool)$value;
    }

    public function getCreatedAt(): ?string
    {
        return $this->getNullable(self::FIELD_CREATED_AT);
    }

    private function getNullable(string $key): ?string
    {
        $value = $this->getData($key);

        return $value === null ? null : (string)$value;
    }
}
