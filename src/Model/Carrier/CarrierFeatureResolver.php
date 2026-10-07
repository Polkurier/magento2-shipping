<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\Carrier\AdditionalData\AdditionalField;
use Polkurier\Webservice\Response\Carrier\AdditionalData\Property;

/**
 * Answers questions about what a carrier supports, from the carrier list's additional data.
 *
 * A carrier listed without additional data is treated as supporting nothing optional.
 */
readonly class CarrierFeatureResolver
{
    /**
     * Services for pallets, which the carrier list doesn't report: offered with every carrier that
     * takes pallets, for pallet shipments, and Polkurier's pricing says which carriers take them.
     */
    public const array PALLET_SERVICES = [
        AdditionalServiceEnum::DangerousGoods,
        AdditionalServiceEnum::DoNotStack,
        AdditionalServiceEnum::TailLiftAtPickup,
        AdditionalServiceEnum::TailLiftAtDelivery,
        AdditionalServiceEnum::SideLoadAtPickup,
        AdditionalServiceEnum::SideUnloadAtDelivery,
    ];

    /**
     * Services offered with every carrier, whatever the carrier list says.
     */
    private const array ALWAYS_OFFERED = [
        AdditionalServiceEnum::SmsOnDispatch,
        AdditionalServiceEnum::SmsOnDispatchWithSenderName,
        AdditionalServiceEnum::SmsOutForDelivery,
        AdditionalServiceEnum::SmsOutForDeliveryWithSenderName,
        AdditionalServiceEnum::CheckContent,
    ];

    public function isForeign(Carrier $carrier): bool
    {
        return $carrier->isForeignShipments();
    }

    public function isRecipientPointRequired(Carrier $carrier): bool
    {
        return $carrier->getAdditionalData()?->getPointsMap()?->getRecipientPoint() === PointRequirementEnum::Required;
    }

    /**
     * Whether the parcels are handed over at a point, and must be: none, optional or required.
     */
    public function getSenderPointRequirement(Carrier $carrier): PointRequirementEnum
    {
        return $carrier->getAdditionalData()?->getPointsMap()?->getSenderPoint() ?? PointRequirementEnum::None;
    }

    public function isRecipientPointAllowed(Carrier $carrier): bool
    {
        $requirement = $carrier->getAdditionalData()?->getPointsMap()?->getRecipientPoint();

        return $requirement !== null && $requirement !== PointRequirementEnum::None;
    }

    /**
     * Map provider ids to hand to the pickup point widget. Not carrier codes.
     *
     * @return string[]
     */
    public function getPointProviders(Carrier $carrier): array
    {
        return \array_values(
            \array_map('strval', $carrier->getAdditionalData()?->getPointsMap()?->getAvailableProviders() ?? []),
        );
    }

    public function isCodAvailable(Carrier $carrier): bool
    {
        return (bool)$carrier->getAdditionalData()?->getCod()?->isAvailable();
    }

    public function isInsuranceRequiredWithCod(Carrier $carrier): bool
    {
        return (bool)$carrier->getAdditionalData()?->getCod()?->isRequireInsurance();
    }

    public function isInsuranceAvailable(Carrier $carrier): bool
    {
        return (bool)$carrier->getAdditionalData()?->getInsurance()?->isAvailable();
    }

    /**
     * @return ShipmentTypeEnum[]
     */
    public function getShipmentTypes(Carrier $carrier): array
    {
        $types = $carrier->getAdditionalData()?->getShipmentType();
        if ($types === null) {
            return [ShipmentTypeEnum::Box];
        }

        return \array_values(\array_filter([
            $types->getEnvelope()->isAvailable() ? ShipmentTypeEnum::Envelope : null,
            $types->getBox()->isAvailable() ? ShipmentTypeEnum::Box : null,
            $types->getPalette()->isAvailable() ? ShipmentTypeEnum::Palette : null,
        ]));
    }

    public function isPickupDateRequired(Carrier $carrier): bool
    {
        return (bool)$carrier->getAdditionalData()?->getPickup()?->getPickupDate()?->isRequired();
    }

    public function isPickupTimeRequired(Carrier $carrier): bool
    {
        $pickup = $carrier->getAdditionalData()?->getPickup();

        return $pickup?->getPickupTimeFrom()?->isRequired() || $pickup?->getPickupTimeTo()?->isRequired();
    }

    public function isPickupDateSupported(Carrier $carrier): bool
    {
        return (bool)$carrier->getAdditionalData()?->getPickup()?->getPickupDate()?->isStatus();
    }

    /**
     * Whether the sender may take the parcel to a point instead of having it collected.
     */
    public function isDropOffAvailable(Carrier $carrier): bool
    {
        return (bool)$carrier->getAdditionalData()?->getPickup()?->getNoCourierOrder()?->isAvailable();
    }

    public function isDropOffDefault(Carrier $carrier): bool
    {
        return (bool)$carrier->getAdditionalData()?->getPickup()?->getNoCourierOrder()?->isDefault();
    }

    /**
     * The additional services the carrier sells, as the codes a shipment is placed with.
     *
     * @return AdditionalServiceEnum[]
     */
    public function getServices(Carrier $carrier): array
    {
        $services = self::ALWAYS_OFFERED;
        if (\in_array(ShipmentTypeEnum::Palette, $this->getShipmentTypes($carrier), true)) {
            $services = [...$services, ...self::PALLET_SERVICES];
        }
        $offered = $carrier->getAdditionalData()?->getCourierService();
        if ($offered === null) {
            return $services;
        }

        $map = [
            [$offered->getReturnOfDocuments(), AdditionalServiceEnum::ReturnOfDocuments],
            [$offered->getCoverSenderAddress(), AdditionalServiceEnum::CoverSenderAddress],
            [$offered->getCarryIn(), AdditionalServiceEnum::CarryIn],
            [$offered->getCourierBringsLabel(), AdditionalServiceEnum::CourierBringsLabel],
            [$offered->getSaturdayDelivery(), AdditionalServiceEnum::SaturdayDelivery],
            [$offered->getPhoneCallBeforeDelivery(), AdditionalServiceEnum::PhoneCallBeforeDelivery],
            [$offered->getPhoneCallBeforeDelivery(), AdditionalServiceEnum::PhoneCallBeforePickup],
            [$offered->getTires(), AdditionalServiceEnum::Tires],
            [$offered->getHandDelivery(), AdditionalServiceEnum::HandDelivery],
            [$offered->getWeekendDelivery(), AdditionalServiceEnum::WeekendDelivery],
            [$offered->getLabelless(), AdditionalServiceEnum::Labelless],
            [$offered->getHandleWithCare(), AdditionalServiceEnum::HandleWithCare],
            [$offered->getDeliveryByTime(), AdditionalServiceEnum::DeliveryByTime],
        ];
        foreach ($map as [$property, $service]) {
            if ($property instanceof Property && $property->isAvailable()) {
                $services[] = $service;
            }
        }

        return $services;
    }

    /**
     * Carrier-specific extra fields the merchant may fill in.
     *
     * @return AdditionalField[]
     */
    public function getAdditionalFields(Carrier $carrier): array
    {
        return \array_values(\array_filter(
            $carrier->getAdditionalData()?->getAdditionalFields() ?? [],
            static fn(AdditionalField $field): bool => $field->isEnabled(),
        ));
    }
}
