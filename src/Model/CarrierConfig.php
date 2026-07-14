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
use Polkurier\Webservice\Enum\PointRequirementEnum;

class CarrierConfig extends AbstractModel implements CarrierConfigInterface
{
    public const string FIELD_CONFIG_ID = 'config_id';
    public const string FIELD_CARRIER_CODE = 'carrier_code';
    public const string FIELD_CARRIER_NAME = 'carrier_name';
    public const string FIELD_CUSTOM_LABEL = 'custom_label';
    public const string FIELD_IMAGE = 'image';
    public const string FIELD_PRICING_MODEL = 'pricing_model';
    public const string FIELD_PRICE_INFO = 'price_info';
    public const string FIELD_FOREIGN_SHIPMENTS = 'foreign_shipments';
    public const string FIELD_COD_AVAILABLE = 'cod_available';
    public const string FIELD_COD_INSURANCE_REQUIRED = 'cod_insurance_required';
    public const string FIELD_INSURANCE_AVAILABLE = 'insurance_available';
    public const string FIELD_SHIPMENT_TYPES = 'shipment_types';
    public const string FIELD_PICKUP_DATE_AVAILABLE = 'pickup_date_available';
    public const string FIELD_PICKUP_DATE_REQUIRED = 'pickup_date_required';
    public const string FIELD_PICKUP_TIME_FROM_AVAILABLE = 'pickup_time_from_available';
    public const string FIELD_PICKUP_TIME_FROM_REQUIRED = 'pickup_time_from_required';
    public const string FIELD_PICKUP_TIME_TO_AVAILABLE = 'pickup_time_to_available';
    public const string FIELD_PICKUP_TIME_TO_REQUIRED = 'pickup_time_to_required';
    public const string FIELD_NO_COURIER_ORDER_AVAILABLE = 'no_courier_order_available';
    public const string FIELD_NO_COURIER_ORDER_DEFAULT = 'no_courier_order_default';
    public const string FIELD_POINTS_MAP_PROVIDERS = 'points_map_providers';
    public const string FIELD_POINTS_MAP_SENDER_POINT = 'points_map_sender_point';
    public const string FIELD_POINTS_MAP_RECIPIENT_POINT = 'points_map_recipient_point';
    public const string FIELD_ADDITIONAL_SERVICES = 'additional_services';
    public const string FIELD_ADDITIONAL_FIELDS = 'additional_fields';
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
     * @return string
     */
    public function getCarrierName(): string
    {
        return $this->getData(static::FIELD_CARRIER_NAME);
    }

    /**
     * @param string $carrierName
     *
     * @return void
     */
    public function setCarrierName(string $carrierName): void
    {
        $this->setData(static::FIELD_CARRIER_NAME, $carrierName);
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
     * @return bool
     */
    public function isForeignShipment(): bool
    {
        return (bool)$this->getData(static::FIELD_FOREIGN_SHIPMENTS);
    }

    /**
     * @param bool $foreignShipment
     *
     * @return void
     */
    public function setForeignShipment(bool $foreignShipment): void
    {
        $this->setData(static::FIELD_FOREIGN_SHIPMENTS, $foreignShipment);
    }

    /**
     * @return bool
     */
    public function isCodAvailable(): bool
    {
        return (bool)$this->getData(static::FIELD_COD_AVAILABLE);
    }

    /**
     * @param bool $codAvailable
     *
     * @return void
     */
    public function setCodAvailable(bool $codAvailable): void
    {
        $this->setData(static::FIELD_COD_AVAILABLE, $codAvailable);
    }

    /**
     * @return bool
     */
    public function isCodInsuranceRequired(): bool
    {
        return (bool)$this->getData(static::FIELD_COD_INSURANCE_REQUIRED);
    }

    /**
     * @param bool $codInsuranceRequired
     *
     * @return void
     */
    public function setCodInsuranceRequired(bool $codInsuranceRequired): void
    {
        $this->setData(static::FIELD_COD_INSURANCE_REQUIRED, $codInsuranceRequired);
    }

    /**
     * @return bool
     */
    public function isInsuranceAvailable(): bool
    {
        return (bool)$this->getData(static::FIELD_INSURANCE_AVAILABLE);
    }

    /**
     * @param bool $insuranceAvailable
     *
     * @return void
     */
    public function setInsuranceAvailable(bool $insuranceAvailable): void
    {
        $this->setData(static::FIELD_INSURANCE_AVAILABLE, $insuranceAvailable);
    }

    /**
     * @return array|null
     */
    public function getShipmentTypes(): ?array
    {
        return $this->getData(static::FIELD_SHIPMENT_TYPES)
            ? \explode(',', $this->getData(static::FIELD_SHIPMENT_TYPES))
            : null;
    }

    /**
     * @param array|null $shipmentTypes
     *
     * @return void
     */
    public function setShipmentTypes(?array $shipmentTypes): void
    {
        $this->setData(static::FIELD_SHIPMENT_TYPES, $shipmentTypes ? \implode(',', $shipmentTypes) : null);
    }

    /**
     * @return bool
     */
    public function isPickupDateAvailable(): bool
    {
        return (bool)$this->getData(static::FIELD_PICKUP_DATE_AVAILABLE);
    }

    /**
     * @param bool $pickupDateAvailable
     *
     * @return void
     */
    public function setPickupDateAvailable(bool $pickupDateAvailable): void
    {
        $this->setData(static::FIELD_PICKUP_DATE_AVAILABLE, $pickupDateAvailable);
    }

    /**
     * @return bool
     */
    public function isPickupDateRequired(): bool
    {
        return (bool)$this->getData(static::FIELD_PICKUP_DATE_REQUIRED);
    }

    /**
     * @param bool $pickupDateRequired
     *
     * @return void
     */
    public function setPickupDateRequired(bool $pickupDateRequired): void
    {
        $this->setData(static::FIELD_PICKUP_DATE_REQUIRED, $pickupDateRequired);
    }

    /**
     * @return bool
     */
    public function isPickupTimeFromAvailable(): bool
    {
        return (bool)$this->getData(static::FIELD_PICKUP_TIME_FROM_AVAILABLE);
    }

    /**
     * @param bool $pickupTimeFromAvailable
     *
     * @return void
     */
    public function setPickupTimeFromAvailable(bool $pickupTimeFromAvailable): void
    {
        $this->setData(static::FIELD_PICKUP_TIME_FROM_AVAILABLE, $pickupTimeFromAvailable);
    }

    /**
     * @return bool
     */
    public function isPickupTimeFromRequired(): bool
    {
        return (bool)$this->getData(static::FIELD_PICKUP_TIME_FROM_REQUIRED);
    }

    /**
     * @param bool $pickupTimeFromRequired
     *
     * @return void
     */
    public function setPickupTimeFromRequired(bool $pickupTimeFromRequired): void
    {
        $this->setData(static::FIELD_PICKUP_TIME_FROM_REQUIRED, $pickupTimeFromRequired);
    }

    /**
     * @return bool
     */
    public function isPickupTimeToAvailable(): bool
    {
        return (bool)$this->getData(static::FIELD_PICKUP_TIME_TO_AVAILABLE);
    }

    /**
     * @param bool $pickupTimeToAvailable
     *
     * @return void
     */
    public function setPickupTimeToAvailable(bool $pickupTimeToAvailable): void
    {
        $this->setData(static::FIELD_PICKUP_TIME_TO_AVAILABLE, $pickupTimeToAvailable);
    }

    /**
     * @return bool
     */
    public function isPickupTimeToRequired(): bool
    {
        return (bool)$this->getData(static::FIELD_PICKUP_TIME_TO_REQUIRED);
    }

    /**
     * @param bool $pickupTimeToRequired
     *
     * @return void
     */
    public function setPickupTimeToRequired(bool $pickupTimeToRequired): void
    {
        $this->setData(static::FIELD_PICKUP_TIME_TO_REQUIRED, $pickupTimeToRequired);
    }

    /**
     * @return bool
     */
    public function isNoCourierOrderAvailable(): bool
    {
        return (bool)$this->getData(static::FIELD_NO_COURIER_ORDER_AVAILABLE);
    }

    /**
     * @param bool $noCourierOrderAvailable
     *
     * @return void
     */
    public function setNoCourierOrderAvailable(bool $noCourierOrderAvailable): void
    {
        $this->setData(static::FIELD_NO_COURIER_ORDER_AVAILABLE, $noCourierOrderAvailable);
    }

    /**
     * @return bool
     */
    public function isNoCourierOrderDefault(): bool
    {
        return (bool)$this->getData(static::FIELD_NO_COURIER_ORDER_DEFAULT);
    }

    /**
     * @param bool $noCourierOrderDefault
     *
     * @return void
     */
    public function setNoCourierOrderDefault(bool $noCourierOrderDefault): void
    {
        $this->setData(static::FIELD_NO_COURIER_ORDER_DEFAULT, $noCourierOrderDefault);
    }

    /**
     * @return array|null
     */
    public function getPointMapProviders(): ?array
    {
        return $this->getData(static::FIELD_POINTS_MAP_PROVIDERS)
            ? \explode(',', $this->getData(static::FIELD_POINTS_MAP_PROVIDERS))
            : null;
    }

    /**
     * @param array|null $pointMapProviders
     *
     * @return void
     */
    public function setPointMapProviders(?array $pointMapProviders): void
    {
        $this->setData(
            static::FIELD_POINTS_MAP_PROVIDERS,
            $pointMapProviders ? \implode(',', $pointMapProviders) : null
        );
    }

    /**
     * @return PointRequirementEnum|null
     */
    public function getPointMapSenderPoint(): ?PointRequirementEnum
    {
        return $this->getData(static::FIELD_POINTS_MAP_SENDER_POINT)
            ? PointRequirementEnum::from($this->getData(static::FIELD_POINTS_MAP_SENDER_POINT))
            : null;
    }

    /**
     * @param PointRequirementEnum|null $senderPoint
     *
     * @return void
     */
    public function setPointMapSenderPoint(?PointRequirementEnum $senderPoint): void
    {
        $this->setData(static::FIELD_POINTS_MAP_SENDER_POINT, $senderPoint?->value);
    }

    /**
     * @return PointRequirementEnum|null
     */
    public function getPointMapRecipientPoint(): ?PointRequirementEnum
    {
        return $this->getData(static::FIELD_POINTS_MAP_RECIPIENT_POINT)
            ? PointRequirementEnum::from($this->getData(static::FIELD_POINTS_MAP_RECIPIENT_POINT))
            : null;
    }

    /**
     * @param PointRequirementEnum|null $recipientPoint
     *
     * @return void
     */
    public function setPointMapRecipientPoint(?PointRequirementEnum $recipientPoint): void
    {
        $this->setData(static::FIELD_POINTS_MAP_RECIPIENT_POINT, $recipientPoint?->value);
    }

    /**
     * @return array|null
     */
    public function getAdditionalServices(): ?array
    {
        return $this->getData(static::FIELD_ADDITIONAL_SERVICES)
            ? \explode(',', $this->getData(static::FIELD_ADDITIONAL_SERVICES))
            : null;
    }

    /**
     * @param array|null $additionalServices
     *
     * @return void
     */
    public function setAdditionalServices(?array $additionalServices): void
    {
        $this->setData(
            static::FIELD_ADDITIONAL_SERVICES,
            $additionalServices ? \implode(',', $additionalServices) : null
        );
    }

    /**
     * @return array|null
     */
    public function getAdditionalFields(): ?array
    {
        return $this->getData(static::FIELD_ADDITIONAL_FIELDS);
    }

    /**
     * @param array|null $additionalFields
     *
     * @return void
     */
    public function setAdditionalFields(?array $additionalFields): void
    {
        $this->setData(static::FIELD_ADDITIONAL_FIELDS, $additionalFields);
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
