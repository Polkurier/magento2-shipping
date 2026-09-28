<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Api\AbstractSimpleObject;
use Polkurier\Shipping\Api\Data\CarrierLabelInterface;

class CarrierLabel extends AbstractSimpleObject implements CarrierLabelInterface
{
    public function getStoreId(): int
    {
        return (int)$this->_get(self::STORE_ID);
    }

    public function setStoreId(int $storeId): static
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getLabel(): string
    {
        return (string)$this->_get(self::LABEL);
    }

    public function setLabel(string $label): static
    {
        return $this->setData(self::LABEL, $label);
    }
}
