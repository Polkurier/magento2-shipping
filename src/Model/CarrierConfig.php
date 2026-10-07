<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\CarrierLabelInterface;

/**
 * The merchant's settings for one carrier.
 */
class CarrierConfig extends AbstractModel implements CarrierConfigInterface
{
    public const string ID_FIELD = self::FIELD_CONFIG_ID;

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_carrier_config';

    /**
     * @var string
     */
    protected $_eventObject = 'carrierConfig';

    public function getCarrierCode(): string
    {
        $value = $this->getData(self::FIELD_CARRIER_CODE);

        return (string)$value;
    }

    public function setCarrierCode(string $carrierCode): static
    {
        return $this->setData(self::FIELD_CARRIER_CODE, $carrierCode);
    }

    public function getCarrierName(): string
    {
        $value = $this->getData(self::FIELD_CARRIER_NAME);

        return (string)$value;
    }

    public function setCarrierName(string $carrierName): static
    {
        return $this->setData(self::FIELD_CARRIER_NAME, $carrierName);
    }

    public function getCustomLabel(): ?string
    {
        $value = $this->getData(self::FIELD_CUSTOM_LABEL);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setCustomLabel(?string $customLabel): static
    {
        return $this->setData(self::FIELD_CUSTOM_LABEL, $customLabel);
    }

    public function getLogo(): ?string
    {
        $value = $this->getData(self::FIELD_LOGO);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setLogo(?string $logo): static
    {
        return $this->setData(self::FIELD_LOGO, $logo);
    }

    /**
     * @return CarrierLabelInterface[]
     */
    public function getStoreLabels(): array
    {
        $value = $this->getData(self::FIELD_STORE_LABELS);

        return \is_array($value) ? \array_values($value) : [];
    }

    /**
     * @param CarrierLabelInterface[] $storeLabels
     */
    public function setStoreLabels(array $storeLabels): static
    {
        return $this->setData(self::FIELD_STORE_LABELS, $storeLabels);
    }

    public function getLabelForStore(int $storeId): string
    {
        foreach ($this->getStoreLabels() as $label) {
            if ($label->getStoreId() === $storeId && $label->getLabel() !== '') {
                return $label->getLabel();
            }
        }

        return $this->getCustomLabel() ?? $this->getCarrierName();
    }

    public function isActive(): bool
    {
        $value = $this->getData(self::FIELD_ACTIVE);

        return (bool)$value;
    }

    public function setActive(bool $active): static
    {
        return $this->setData(self::FIELD_ACTIVE, (int)$active);
    }

    public function getSortOrder(): int
    {
        $value = $this->getData(self::FIELD_SORT_ORDER);

        return (int)$value;
    }

    public function setSortOrder(int $sortOrder): static
    {
        return $this->setData(self::FIELD_SORT_ORDER, $sortOrder);
    }

    public function getPricingModel(): string
    {
        $value = $this->getData(self::FIELD_PRICING_MODEL);

        return (string)$value;
    }

    public function setPricingModel(string $pricingModel): static
    {
        return $this->setData(self::FIELD_PRICING_MODEL, $pricingModel);
    }

    public function getPriceInfo(): array
    {
        $value = $this->getData(self::FIELD_PRICE_INFO);
        if (\is_array($value)) {
            return $value;
        }
        $decoded = \is_string($value) && $value !== '' ? \json_decode($value, true) : [];

        return \is_array($decoded) ? $decoded : [];
    }

    public function setPriceInfo(array $priceInfo): static
    {
        return $this->setData(self::FIELD_PRICE_INFO, \json_encode($priceInfo, \JSON_THROW_ON_ERROR));
    }

    public function isAvailable(): bool
    {
        $value = $this->getData(self::FIELD_AVAILABLE);

        return (bool)$value;
    }

    public function setAvailable(bool $available): static
    {
        return $this->setData(self::FIELD_AVAILABLE, (int)$available);
    }

    protected function _construct(): void
    {
        $this->_init(ResourceModel\CarrierConfig::class);
    }
}
