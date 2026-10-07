<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * The merchant's settings for one Polkurier carrier.
 *
 * @api
 */
interface CarrierConfigInterface
{
    public const string FIELD_CONFIG_ID = 'config_id';
    public const string FIELD_CARRIER_CODE = 'carrier_code';
    public const string FIELD_CARRIER_NAME = 'carrier_name';
    public const string FIELD_CUSTOM_LABEL = 'custom_label';
    public const string FIELD_LOGO = 'logo';
    public const string FIELD_ACTIVE = 'active';
    public const string FIELD_SORT_ORDER = 'sort_order';
    public const string FIELD_PRICING_MODEL = 'pricing_model';
    public const string FIELD_PRICE_INFO = 'price_info';
    public const string FIELD_AVAILABLE = 'available';
    public const string FIELD_STORE_LABELS = 'store_labels';

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @return string
     */
    public function getCarrierCode(): string;

    /**
     * @param string $carrierCode
     *
     * @return $this
     */
    public function setCarrierCode(string $carrierCode): static;

    /**
     * @return string
     */
    public function getCarrierName(): string;

    /**
     * @param string $carrierName
     *
     * @return $this
     */
    public function setCarrierName(string $carrierName): static;

    /**
     * @return string|null
     */
    public function getCustomLabel(): ?string;

    /**
     * @param string|null $customLabel
     *
     * @return $this
     */
    public function setCustomLabel(?string $customLabel): static;

    /**
     * The logo shown next to the method in checkout: a path relative to the media directory.
     *
     * @return string|null
     */
    public function getLogo(): ?string;

    /**
     * @param string|null $logo
     *
     * @return $this
     */
    public function setLogo(?string $logo): static;

    /**
     * Labels for single store views, overriding the custom label there.
     *
     * @return \Polkurier\Shipping\Api\Data\CarrierLabelInterface[]
     */
    public function getStoreLabels(): array;

    /**
     * @param \Polkurier\Shipping\Api\Data\CarrierLabelInterface[] $storeLabels
     *
     * @return $this
     */
    public function setStoreLabels(array $storeLabels): static;

    /**
     * The name customers see in a store view: the store view's label, else the custom label, else
     * the carrier's own name.
     *
     * @param int $storeId
     *
     * @return string
     */
    public function getLabelForStore(int $storeId): string;

    /**
     * @return bool
     */
    public function isActive(): bool;

    /**
     * @param bool $active
     *
     * @return $this
     */
    public function setActive(bool $active): static;

    /**
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * @param int $sortOrder
     *
     * @return $this
     */
    public function setSortOrder(int $sortOrder): static;

    /**
     * @return string
     */
    public function getPricingModel(): string;

    /**
     * @param string $pricingModel
     *
     * @return $this
     */
    public function setPricingModel(string $pricingModel): static;

    /**
     * @return mixed[]
     */
    public function getPriceInfo(): array;

    /**
     * @param mixed[] $priceInfo
     *
     * @return $this
     */
    public function setPriceInfo(array $priceInfo): static;

    /**
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * @param bool $available
     *
     * @return $this
     */
    public function setAvailable(bool $available): static;
}
