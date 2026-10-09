<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\CarrierConfig;

use Magento\Framework\Api\AbstractSimpleObject;
use Polkurier\Shipping\Api\Data\CarrierLabelInterface;

/**
 * A carrier's label in one store view.
 */
class CarrierLabel extends AbstractSimpleObject implements CarrierLabelInterface
{
    public function getStoreId(): int
    {
        return (int)$this->_get(self::FIELD_STORE_ID);
    }

    public function setStoreId(int $storeId): static
    {
        return $this->setData(self::FIELD_STORE_ID, $storeId);
    }

    public function getLabel(): string
    {
        return (string)$this->_get(self::FIELD_LABEL);
    }

    public function setLabel(string $label): static
    {
        return $this->setData(self::FIELD_LABEL, $label);
    }
}
