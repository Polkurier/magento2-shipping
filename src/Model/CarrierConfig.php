<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Enum\PricingModelEnum;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig as CarrierConfigResource;

class CarrierConfig extends AbstractModel implements CarrierConfigInterface
{
    public const string FIELD_CONFIG_ID = 'config_id';
    public const string FIELD_CARRIER_CODE = 'carrier_code';
    public const string FIELD_CUSTOM_LABEL = 'custom_label';
    public const string FIELD_IMAGE = 'image';
    public const string FIELD_PRICING_MODEL = 'pricing_model';
    public const string FIELD_PRICE_INFO = 'price_info';
    public const string FIELD_EXT_DATA = 'ext_data';
    public const string FIELD_SORT_ORDER = 'sort_order';
    public const string FIELD_ACTIVE = 'active';

    public const PricingModelEnum DEFAULT_PRICING_MODEL = PricingModelEnum::FIXED;

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_carrier_config';

    /**
     * @var string
     */
    protected $_eventObject = 'polkurier_carrier_config';

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(CarrierConfigResource::class);
        $this->setPricingModel(static::DEFAULT_PRICING_MODEL);
    }

    /**
     * @return int|null
     */
    public function getConfigId(): ?int
    {
        return $this->getData(static::FIELD_CONFIG_ID);
    }

    /**
     * @param int|null $configId
     *
     * @return void
     */
    public function setConfigId(?int $configId): void
    {
        $this->setData(static::FIELD_CONFIG_ID, $configId);
    }

    /**
     * @return string
     */
    public function getCarrierCode(): string
    {
        return $this->getData(static::FIELD_CARRIER_CODE);
    }

    /**
     * @param string $carrierCode
     *
     * @return void
     */
    public function setCarrierCode(string $carrierCode): void
    {
        $this->setData(static::FIELD_CARRIER_CODE, $carrierCode);
    }

    /**
     * @return string|null
     */
    public function getCustomLabel(): ?string
    {
        return $this->getData(static::FIELD_CUSTOM_LABEL);
    }

    /**
     * @param string|null $customLabel
     *
     * @return void
     */
    public function setCustomLabel(?string $customLabel): void
    {
        $this->setData(static::FIELD_CUSTOM_LABEL, $customLabel);
    }

    /**
     * @return string|null
     */
    public function getImage(): ?string
    {
        return $this->getData(static::FIELD_IMAGE);
    }

    /**
     * @param string|null $image
     *
     * @return void
     */
    public function setImage(?string $image): void
    {
        $this->setData(static::FIELD_IMAGE, $image);
    }

    /**
     * @return PricingModelEnum
     */
    public function getPricingModel(): PricingModelEnum
    {
        return PricingModelEnum::from($this->getData(static::FIELD_PRICING_MODEL));
    }

    /**
     * @param PricingModelEnum $pricingModel
     *
     * @return void
     */
    public function setPricingModel(PricingModelEnum $pricingModel): void
    {
        $this->setData(static::FIELD_PRICING_MODEL, $pricingModel->value);
    }

    /**
     * @return array|null
     */
    public function getPriceInfo(): ?array
    {
        return $this->getData(static::FIELD_PRICE_INFO);
    }

    /**
     * @param array $priceInfo
     *
     * @return void
     */
    public function setPriceInfo(array $priceInfo): void
    {
        $this->setData(static::FIELD_PRICE_INFO, $priceInfo);
    }

    /**
     * @return array
     */
    public function getExtData(): array
    {
        return $this->getData(static::FIELD_EXT_DATA);
    }

    /**
     * @param array $extData
     *
     * @return void
     */
    public function setExtData(array $extData): void
    {
        $this->setData(static::FIELD_EXT_DATA, $extData);
    }

    /**
     * @return int
     */
    public function getSortOrder(): int
    {
        return (int)$this->getData(static::FIELD_SORT_ORDER);
    }

    /**
     * @param int $sortOrder
     *
     * @return void
     */
    public function setSortOrder(int $sortOrder): void
    {
        $this->setData(static::FIELD_SORT_ORDER, $sortOrder);
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool)$this->getData(static::FIELD_ACTIVE);
    }

    /**
     * @param bool $active
     *
     * @return void
     */
    public function setActive(bool $active): void
    {
        $this->setData(static::FIELD_ACTIVE, $active);
    }
}
