<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\DataObject;
use Polkurier\Shipping\Api\Data\ShippingChoiceInterface;

class ShippingChoice extends DataObject implements ShippingChoiceInterface
{
    public function getOrderId(): int
    {
        return (int)$this->getData(self::ORDER_ID);
    }

    public function getShippingMethod(): ?string
    {
        return $this->nullable(self::SHIPPING_METHOD);
    }

    public function getShippingDescription(): ?string
    {
        return $this->nullable(self::SHIPPING_DESCRIPTION);
    }

    public function getPointMethodCode(): ?string
    {
        return $this->nullable(self::POINT_METHOD_CODE);
    }

    public function getPointId(): ?string
    {
        return $this->nullable(self::POINT_ID);
    }

    public function getPointLabel(): ?string
    {
        return $this->nullable(self::POINT_LABEL);
    }

    public function isPointCod(): bool
    {
        return (bool)$this->getData(self::POINT_COD);
    }

    public function getCreatedAt(): ?string
    {
        return $this->nullable(self::CREATED_AT);
    }

    private function nullable(string $key): ?string
    {
        $value = $this->getData($key);

        return $value === null ? null : (string)$value;
    }
}
