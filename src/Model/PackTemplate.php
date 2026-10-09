<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Model\Shipment\Placement\ParcelTypeMapper;
use Polkurier\Shipping\Model\Sync\SyncableTrait;

/**
 * A pack template kept in Magento and mirrored with Polkurier.
 */
class PackTemplate extends AbstractModel implements PackTemplateInterface
{
    use SyncableTrait;

    public const string ID_FIELD = 'template_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_pack_template';

    /**
     * @var string
     */
    protected $_eventObject = 'packTemplate';

    /**
     * Services arrive from forms as a list; the column holds them comma-separated.
     */
    public function beforeSave(): static
    {
        $services = $this->getData(self::FIELD_SERVICES);
        if (\is_array($services)) {
            $this->setData(self::FIELD_SERVICES, \implode(',', \array_filter(\array_map('strval', $services))));
        }
        // Left empty in a form: not set, rather than none.
        $tare = $this->getData(self::FIELD_TARE);
        if ($tare !== null && !\is_float($tare)) {
            $tare = \trim(\str_replace(',', '.', (string)$tare));
            $this->setData(self::FIELD_TARE, $tare === '' ? null : (float)$tare);
        }

        return parent::beforeSave();
    }

    public function getName(): string
    {
        $value = $this->getData(self::FIELD_NAME);

        return (string)$value;
    }

    public function setName(string $name): static
    {
        return $this->setData(self::FIELD_NAME, $name);
    }

    public function getDescription(): ?string
    {
        $value = $this->getData(self::FIELD_DESCRIPTION);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setDescription(?string $description): static
    {
        return $this->setData(self::FIELD_DESCRIPTION, $description);
    }

    public function getShipmentType(): string
    {
        return ParcelTypeMapper::shipmentTypeOf($this->getPackType())->value;
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

    public function getTare(): ?float
    {
        $value = $this->getData(self::FIELD_TARE);

        return $value === null || $value === '' ? null : (float)$value;
    }

    public function setTare(?float $tare): static
    {
        return $this->setData(self::FIELD_TARE, $tare);
    }

    public function getCodAmount(): float
    {
        $value = $this->getData(self::FIELD_COD_AMOUNT);

        return (float)$value;
    }

    public function setCodAmount(float $codAmount): static
    {
        return $this->setData(self::FIELD_COD_AMOUNT, $codAmount);
    }

    public function getInsuranceAmount(): float
    {
        $value = $this->getData(self::FIELD_INSURANCE_AMOUNT);

        return (float)$value;
    }

    public function setInsuranceAmount(float $insuranceAmount): static
    {
        return $this->setData(self::FIELD_INSURANCE_AMOUNT, $insuranceAmount);
    }

    public function getCarrierCode(): ?string
    {
        $value = $this->getData(self::FIELD_CARRIER_CODE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setCarrierCode(?string $carrierCode): static
    {
        return $this->setData(self::FIELD_CARRIER_CODE, $carrierCode);
    }

    public function getServices(): array
    {
        $value = $this->getData(self::FIELD_SERVICES);
        if (\is_array($value)) {
            return \array_values(\array_map('strval', $value));
        }

        return \array_values(\array_filter(
            \explode(',', (string)$value),
            static fn(string $item): bool => $item !== '',
        ));
    }

    public function setServices(array $services): static
    {
        return $this->setData(self::FIELD_SERVICES, \implode(',', $services));
    }

    public function isDefault(): bool
    {
        $value = $this->getData(self::FIELD_IS_DEFAULT);

        return (bool)$value;
    }

    public function setDefault(bool $default): static
    {
        return $this->setData(self::FIELD_IS_DEFAULT, (int)$default);
    }

    protected function _construct(): void
    {
        $this->_init(ResourceModel\PackTemplate::class);
    }
}
