<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

use Polkurier\Shipping\Enum\PricingModelEnum;
use Polkurier\Webservice\Enum\PointRequirementEnum;

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
     * @return string
     */
    public function getCarrierName(): string;

    /**
     * @param string $carrierName
     *
     * @return void
     */
    public function setCarrierName(string $carrierName): void;

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
     * @return PricingModelEnum
     */
    public function getPricingModel(): PricingModelEnum;

    /**
     * @param PricingModelEnum $pricingModel
     *
     * @return void
     */
    public function setPricingModel(PricingModelEnum $pricingModel): void;

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
     * @return bool
     */
    public function isForeignShipment(): bool;

    /**
     * @param bool $foreignShipment
     *
     * @return void
     */
    public function setForeignShipment(bool $foreignShipment): void;

    /**
     * @return bool
     */
    public function isCodAvailable(): bool;

    /**
     * @param bool $codAvailable
     *
     * @return void
     */
    public function setCodAvailable(bool $codAvailable): void;

    /**
     * @return bool
     */
    public function isCodInsuranceRequired(): bool;

    /**
     * @param bool $codInsuranceRequired
     *
     * @return void
     */
    public function setCodInsuranceRequired(bool $codInsuranceRequired): void;

    /**
     * @return bool
     */
    public function isInsuranceAvailable(): bool;

    /**
     * @param bool $insuranceAvailable
     *
     * @return void
     */
    public function setInsuranceAvailable(bool $insuranceAvailable): void;

    /**
     * @return array|null
     */
    public function getShipmentTypes(): ?array;

    /**
     * @param array|null $shipmentTypes
     *
     * @return void
     */
    public function setShipmentTypes(?array $shipmentTypes): void;

    /**
     * @return bool
     */
    public function isPickupDateAvailable(): bool;

    /**
     * @param bool $pickupDateAvailable
     *
     * @return void
     */
    public function setPickupDateAvailable(bool $pickupDateAvailable): void;

    /**
     * @return bool
     */
    public function isPickupDateRequired(): bool;

    /**
     * @param bool $pickupDateRequired
     *
     * @return void
     */
    public function setPickupDateRequired(bool $pickupDateRequired): void;

    /**
     * @return bool
     */
    public function isPickupTimeFromAvailable(): bool;

    /**
     * @param bool $pickupTimeFromAvailable
     *
     * @return void
     */
    public function setPickupTimeFromAvailable(bool $pickupTimeFromAvailable): void;

    /**
     * @return bool
     */
    public function isPickupTimeFromRequired(): bool;

    /**
     * @param bool $pickupTimeFromRequired
     *
     * @return void
     */
    public function setPickupTimeFromRequired(bool $pickupTimeFromRequired): void;

    /**
     * @return bool
     */
    public function isPickupTimeToAvailable(): bool;

    /**
     * @param bool $pickupTimeToAvailable
     *
     * @return void
     */
    public function setPickupTimeToAvailable(bool $pickupTimeToAvailable): void;

    /**
     * @return bool
     */
    public function isPickupTimeToRequired(): bool;

    /**
     * @param bool $pickupTimeToRequired
     *
     * @return void
     */
    public function setPickupTimeToRequired(bool $pickupTimeToRequired): void;

    /**
     * @return bool
     */
    public function isNoCourierOrderAvailable(): bool;

    /**
     * @param bool $noCourierOrderAvailable
     *
     * @return void
     */
    public function setNoCourierOrderAvailable(bool $noCourierOrderAvailable): void;

    /**
     * @return bool
     */
    public function isNoCourierOrderDefault(): bool;

    /**
     * @param bool $noCourierOrderDefault
     *
     * @return void
     */
    public function setNoCourierOrderDefault(bool $noCourierOrderDefault): void;

    /**
     * @return array|null
     */
    public function getPointMapProviders(): ?array;

    /**
     * @param array|null $pointMapProviders
     *
     * @return void
     */
    public function setPointMapProviders(?array $pointMapProviders): void;

    /**
     * @return PointRequirementEnum|null
     */
    public function getPointMapSenderPoint(): ?PointRequirementEnum;

    /**
     * @param PointRequirementEnum|null $senderPoint
     *
     * @return void
     */
    public function setPointMapSenderPoint(?PointRequirementEnum $senderPoint): void;

    /**
     * @return PointRequirementEnum|null
     */
    public function getPointMapRecipientPoint(): ?PointRequirementEnum;

    /**
     * @param PointRequirementEnum|null $recipientPoint
     *
     * @return void
     */
    public function setPointMapRecipientPoint(?PointRequirementEnum $recipientPoint): void;

    /**
     * @return array|null
     */
    public function getAdditionalServices(): ?array;

    /**
     * @param array|null $additionalServices
     *
     * @return void
     */
    public function setAdditionalServices(?array $additionalServices): void;

    /**
     * @return array|null
     */
    public function getAdditionalFields(): ?array;

    /**
     * @param array|null $additionalFields
     *
     * @return void
     */
    public function setAdditionalFields(?array $additionalFields): void;

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
