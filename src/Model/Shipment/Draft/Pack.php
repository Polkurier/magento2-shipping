<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Draft;

use Magento\Framework\DataObject;
use Polkurier\Shipping\Api\Data\ShipmentDraft\PackInterface;

/**
 * One parcel of a shipment draft.
 */
class Pack extends DataObject implements PackInterface
{
    public function getTemplateId(): ?int
    {
        $value = $this->getData(self::TEMPLATE_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setTemplateId(?int $templateId): static
    {
        return $this->setData(self::TEMPLATE_ID, $templateId);
    }

    public function getPackType(): string
    {
        $value = $this->getData(self::PACK_TYPE);

        return (string)$value;
    }

    public function setPackType(string $packType): static
    {
        return $this->setData(self::PACK_TYPE, $packType);
    }

    public function getLength(): int
    {
        $value = $this->getData(self::LENGTH);

        return (int)$value;
    }

    public function setLength(int $length): static
    {
        return $this->setData(self::LENGTH, $length);
    }

    public function getWidth(): int
    {
        $value = $this->getData(self::WIDTH);

        return (int)$value;
    }

    public function setWidth(int $width): static
    {
        return $this->setData(self::WIDTH, $width);
    }

    public function getHeight(): int
    {
        $value = $this->getData(self::HEIGHT);

        return (int)$value;
    }

    public function setHeight(int $height): static
    {
        return $this->setData(self::HEIGHT, $height);
    }

    public function getWeight(): float
    {
        $value = $this->getData(self::WEIGHT);

        return (float)$value;
    }

    public function setWeight(float $weight): static
    {
        return $this->setData(self::WEIGHT, $weight);
    }

    public function getAmount(): int
    {
        $value = $this->getData(self::AMOUNT);

        return (int)$value;
    }

    public function setAmount(int $amount): static
    {
        return $this->setData(self::AMOUNT, $amount);
    }
}
