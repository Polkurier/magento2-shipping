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
        $value = $this->getData(self::FIELD_TEMPLATE_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setTemplateId(?int $templateId): static
    {
        return $this->setData(self::FIELD_TEMPLATE_ID, $templateId);
    }

    public function getPackType(): string
    {
        $value = $this->getData(self::FIELD_PACK_TYPE);

        return (string)$value;
    }

    public function setPackType(string $packType): static
    {
        return $this->setData(self::FIELD_PACK_TYPE, $packType);
    }

    public function getLength(): int
    {
        $value = $this->getData(self::FIELD_LENGTH);

        return (int)$value;
    }

    public function setLength(int $length): static
    {
        return $this->setData(self::FIELD_LENGTH, $length);
    }

    public function getWidth(): int
    {
        $value = $this->getData(self::FIELD_WIDTH);

        return (int)$value;
    }

    public function setWidth(int $width): static
    {
        return $this->setData(self::FIELD_WIDTH, $width);
    }

    public function getHeight(): int
    {
        $value = $this->getData(self::FIELD_HEIGHT);

        return (int)$value;
    }

    public function setHeight(int $height): static
    {
        return $this->setData(self::FIELD_HEIGHT, $height);
    }

    public function getWeight(): float
    {
        $value = $this->getData(self::FIELD_WEIGHT);

        return (float)$value;
    }

    public function setWeight(float $weight): static
    {
        return $this->setData(self::FIELD_WEIGHT, $weight);
    }

    public function getAmount(): int
    {
        $value = $this->getData(self::FIELD_AMOUNT);

        return (int)$value;
    }

    public function setAmount(int $amount): static
    {
        return $this->setData(self::FIELD_AMOUNT, $amount);
    }

    public function getKeepAs(): string
    {
        return \trim((string)$this->getData(self::FIELD_KEEP_AS));
    }

    public function setKeepAs(string $name): static
    {
        return $this->setData(self::FIELD_KEEP_AS, $name);
    }

    public function getItems(): array
    {
        $value = $this->getData(self::FIELD_ITEMS);

        return \is_array($value) ? $value : [];
    }

    public function setItems(array $items): static
    {
        return $this->setData(self::FIELD_ITEMS, $items);
    }
}
