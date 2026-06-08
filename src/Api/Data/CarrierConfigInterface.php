<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

interface CarrierConfigInterface
{
    /**
     * @return int|null
     */
    public function getConfigId(): ?int;

    /**
     * @param int|null $configId
     *
     * @return void
     */
    public function setConfigId(?int $configId): void;

    /**
     * @return string
     */
    public function getCarrierCode(): string;

    /**
     * @param string $carrierCode
     *
     * @return void
     */
    public function setCarrierCode(string $carrierCode): void;

    /**
     * @return string|null
     */
    public function getCustomLabel(): ?string;

    /**
     * @param string|null $customLabel
     *
     * @return void
     */
    public function setCustomLabel(?string $customLabel): void;

    /**
     * @return string|null
     */
    public function getImage(): ?string;

    /**
     * @param string|null $image
     *
     * @return void
     */
    public function setImage(?string $image): void;

    /**
     * @return \Polkurier\Shipping\Enum\PricingModelEnum
     */
    public function getPricingModel(): \Polkurier\Shipping\Enum\PricingModelEnum;

    /**
     * @param \Polkurier\Shipping\Enum\PricingModelEnum $pricingModel
     *
     * @return void
     */
    public function setPricingModel(\Polkurier\Shipping\Enum\PricingModelEnum $pricingModel): void;

    /**
     * @return array|null
     */
    public function getPriceInfo(): ?array;

    /**
     * @param array $priceInfo
     *
     * @return void
     */
    public function setPriceInfo(array $priceInfo): void;

    /**
     * @return array
     */
    public function getExtData(): array;

    /**
     * @param array $extData
     *
     * @return void
     */
    public function setExtData(array $extData): void;

    /**
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * @param int $sortOrder
     *
     * @return void
     */
    public function setSortOrder(int $sortOrder): void;

    /**
     * @return bool
     */
    public function isActive(): bool;

    /**
     * @param bool $active
     *
     * @return void
     */
    public function setActive(bool $active): void;
}
