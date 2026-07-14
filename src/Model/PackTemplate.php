<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\ExternalEntityInterface;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate as PackTemplateResource;
use Polkurier\Webservice\Enum\AdditionalServiceEnum;
use Polkurier\Webservice\Enum\ShipmentTypeEnum;

class PackTemplate extends AbstractPack implements PackTemplateInterface, ExternalEntityInterface
{
    use ExternalEntityTrait;

    public const string FIELD_TEMPLATE_ID = 'template_id';
    public const string FIELD_NAME = 'name';
    public const string FIELD_DESCRIPTION = 'description';
    public const string FIELD_SHIPMENT_TYPE = 'shipment_type';
    public const string FIELD_COD = 'cod';
    public const string FIELD_INSURANCE = 'insurance';
    public const string FIELD_CARRIER_CODE = 'carrier_code';
    public const string FIELD_SERVICES = 'services';
    public const string FIELD_DEFAULT = 'default';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_pack_template';

    /**
     * @var string
     */
    protected $_eventObject = 'polkurier_pack_template';

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
        return (int)$this->getData(static::FIELD_TEMPLATE_ID) ?: null;
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
        return ShipmentTypeEnum::from($this->getData(static::FIELD_SHIPMENT_TYPE));
    }

    /**
     * @param ShipmentTypeEnum $shipmentType
     *
     * @return void
     */
    public function setShipmentType(ShipmentTypeEnum $shipmentType): void
    {
        $this->setData(static::FIELD_SHIPMENT_TYPE, $shipmentType->value);
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
        return \array_map(
            fn (string $service) => AdditionalServiceEnum::from($service),
            \explode(',', (string)$this->getData(static::FIELD_SERVICES))
        );
    }

    /**
     * @param AdditionalServiceEnum[] $services
     *
     * @return void
     */
    public function setServices(array $services): void
    {
        $this->setData(
            static::FIELD_SERVICES,
            \implode(',', \array_map(fn (AdditionalServiceEnum $service) => $service->value, $services))
        );
    }

    /**
     * @return bool
     */
    public function isDefault(): bool
    {
        return (bool)$this->getData(static::FIELD_DEFAULT);
    }

    /**
     * @param bool $default
     *
     * @return void
     */
    public function setDefault(bool $default): void
    {
        $this->setData(static::FIELD_DEFAULT, $default);
    }
}
