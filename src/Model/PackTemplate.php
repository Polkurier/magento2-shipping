<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\ExternalEntityInterface;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate as PackTemplateResource;
use Polkurier\Webservice\Api\Data\AdditionalServiceEnum;
use Polkurier\Webservice\Api\Data\PackTypeEnum;
use Polkurier\Webservice\Api\Data\ShipmentTypeEnum;

class PackTemplate extends AbstractModel implements PackTemplateInterface, ExternalEntityInterface
{
    public const string FIELD_TEMPLATE_ID = 'template_id';
    public const string FIELD_EXT_ID = 'ext_id';
    public const string FIELD_CREATED_AT = 'created_at';
    public const string FIELD_UPDATED_AT = 'updated_at';
    public const string FIELD_NAME = 'name';
    public const string FIELD_DESCRIPTION = 'description';
    public const string FIELD_TYPE = 'type';
    public const string FIELD_LENGTH = 'length';
    public const string FIELD_HEIGHT = 'height';
    public const string FIELD_WIDTH = 'width';
    public const string FIELD_WEIGHT = 'weight';
    public const string FIELD_SHIPMENT_TYPE = 'shipment_type';
    public const string FIELD_COD = 'cod';
    public const string FIELD_INSURANCE = 'insurance';
    public const string FIELD_CARRIER_CODE = 'carrier_code';
    public const string FIELD_SERVICES = 'services';

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(PackTemplateResource::class);
    }

    /**
     * @return int|null
     */
    public function getTemplateId(): ?int
    {
        return $this->getData(static::FIELD_TEMPLATE_ID);
    }

    /**
     * @param int|null $templateId
     *
     * @return void
     */
    public function setTemplateId(?int $templateId): void
    {
        $this->setData(static::FIELD_TEMPLATE_ID, $templateId);
    }

    /**
     * @return string|null
     */
    public function getExtId(): ?string
    {
        return $this->getData(static::FIELD_EXT_ID);
    }

    /**
     * @param string|null $extId
     *
     * @return void
     */
    public function setExtId(?string $extId): void
    {
        $this->setData(static::FIELD_EXT_ID, $extId);
    }

    /**
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->getData(static::FIELD_CREATED_AT);
    }

    /**
     * @param \DateTimeInterface $createdAt
     *
     * @return void
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->setData(static::FIELD_CREATED_AT, $createdAt);
    }

    /**
     * @return \DateTimeInterface
     */
    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->getData(static::FIELD_UPDATED_AT);
    }

    /**
     * @param \DateTimeInterface $updatedAt
     *
     * @return void
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->setData(static::FIELD_UPDATED_AT, $updatedAt);
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return $this->getData(static::FIELD_LENGTH);
    }

    /**
     * @param int $length
     *
     * @return void
     */
    public function setLength(int $length): void
    {
        $this->setData(static::FIELD_LENGTH, $length);
    }

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return $this->getData(static::FIELD_WIDTH);
    }

    /**
     * @param int $width
     *
     * @return void
     */
    public function setWidth(int $width): void
    {
        $this->setData(static::FIELD_WIDTH, $width);
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return $this->getData(static::FIELD_HEIGHT);
    }

    /**
     * @param int $height
     *
     * @return void
     */
    public function setHeight(int $height): void
    {
        $this->setData(static::FIELD_HEIGHT, $height);
    }

    /**
     * @return float
     */
    public function getWeight(): float
    {
        return $this->getData(static::FIELD_WEIGHT);
    }

    /**
     * @param float $weight
     *
     * @return void
     */
    public function setWeight(float $weight): void
    {
        $this->setData(static::FIELD_WEIGHT, $weight);
    }

    /**
     * @return PackTypeEnum
     */
    public function getType(): PackTypeEnum
    {
        return $this->getData(static::FIELD_TYPE);
    }

    /**
     * @param PackTypeEnum $type
     *
     * @return void
     */
    public function setType(PackTypeEnum $type): void
    {
        $this->setData(static::FIELD_TYPE, $type);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->getData(static::FIELD_NAME);
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setName(string $name): void
    {
        $this->setData(static::FIELD_NAME, $name);
    }

    /**
     * @return ShipmentTypeEnum
     */
    public function getShipmentType(): ShipmentTypeEnum
    {
        return $this->getData(static::FIELD_SHIPMENT_TYPE);
    }

    /**
     * @param ShipmentTypeEnum $shipmentType
     *
     * @return void
     */
    public function setShipmentType(ShipmentTypeEnum $shipmentType): void
    {
        $this->setData(static::FIELD_SHIPMENT_TYPE, $shipmentType);
    }

    /**
     * @return float
     */
    public function getCod(): float
    {
        return $this->getData(static::FIELD_COD);
    }

    /**
     * @param float $cod
     *
     * @return void
     */
    public function setCod(float $cod): void
    {
        $this->setData(static::FIELD_COD, $cod);
    }

    /**
     * @return float
     */
    public function getInsurance(): float
    {
        return $this->getData(static::FIELD_INSURANCE);
    }

    /**
     * @param float $insurance
     *
     * @return void
     */
    public function setInsurance(float $insurance): void
    {
        $this->setData(static::FIELD_INSURANCE, $insurance);
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
     * @return string|null
     */
    public function getCourier(): ?string
    {
        return $this->getData(static::FIELD_CARRIER_CODE);
    }

    /**
     * @param string|null $courier
     *
     * @return void
     */
    public function setCourier(?string $courier): void
    {
        $this->setData(static::FIELD_CARRIER_CODE, $courier);
    }

    /**
     * @return AdditionalServiceEnum[]
     */
    public function getServices(): array
    {
        return $this->getData(static::FIELD_SERVICES);
    }

    /**
     * @param AdditionalServiceEnum[] $services
     *
     * @return void
     */
    public function setServices(array $services): void
    {
        $this->setData(static::FIELD_SERVICES, $services);
    }
}
