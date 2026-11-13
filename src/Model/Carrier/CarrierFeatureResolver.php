<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Polkurier\Webservice\Dto\Response\Carrier;
use Polkurier\Webservice\Dto\Response\Carrier\AdditionalData\AdditionalField;
use Polkurier\Webservice\Dto\Response\Carrier\AdditionalData\Property;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;

/**
 * Answers questions about what a carrier supports, from the carrier list's additional data.
 *
 * A carrier listed without additional data is treated as supporting nothing optional.
 */
readonly class CarrierFeatureResolver
{
    /**
     * Services for pallets, which the carrier list doesn't report. They're offered for pallet
     * shipments with every carrier that takes pallets; Polkurier's pricing says which carriers do.
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

    public function isRecipientPointRequired(Carrier $carrier): bool
    {
        return $carrier->additionalData?->pointsMap?->recipientPoint === PointRequirementEnum::Required;
    }

    /**
     * Whether the sender may or must hand the parcels over at a point: none, optional or required.
     */
    public function getSenderPointRequirement(Carrier $carrier): PointRequirementEnum
    {
        return $carrier->additionalData->pointsMap->senderPoint ?? PointRequirementEnum::None;
    }

    public function isRecipientPointAllowed(Carrier $carrier): bool
    {
        $requirement = $carrier->additionalData?->pointsMap?->recipientPoint;

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
            \array_map('strval', $carrier->additionalData->pointsMap->availableProviders ?? []),
        );
    }

    public function isCodAvailable(Carrier $carrier): bool
    {
        return (bool)$carrier->additionalData?->cod?->available;
    }

    public function isInsuranceRequiredWithCod(Carrier $carrier): bool
    {
        return (bool)$carrier->additionalData?->cod?->requireInsurance;
    }

    public function isInsuranceAvailable(Carrier $carrier): bool
    {
        return (bool)$carrier->additionalData?->insurance?->available;
    }

    /**
     * @return ShipmentTypeEnum[]
     */
    public function getShipmentTypes(Carrier $carrier): array
    {
        $types = $carrier->additionalData?->shipmentType;
        if ($types === null) {
            return [ShipmentTypeEnum::Box];
        }

        return \array_values(\array_filter([
            $types->envelope->available ? ShipmentTypeEnum::Envelope : null,
            $types->box->available ? ShipmentTypeEnum::Box : null,
            $types->palette->available ? ShipmentTypeEnum::Palette : null,
        ]));
    }

    public function isPickupDateRequired(Carrier $carrier): bool
    {
        return (bool)$carrier->additionalData?->pickup?->pickupDate?->required;
    }

    public function isPickupTimeRequired(Carrier $carrier): bool
    {
        $pickup = $carrier->additionalData?->pickup;

        return $pickup?->pickupTimeFrom?->required || $pickup?->pickupTimeTo?->required;
    }

    public function isPickupDateSupported(Carrier $carrier): bool
    {
        return (bool)$carrier->additionalData?->pickup?->pickupDate?->status;
    }

    /**
     * Whether the sender may take the parcel to a point instead of having it collected.
     */
    public function isDropOffAvailable(Carrier $carrier): bool
    {
        return (bool)$carrier->additionalData?->pickup?->noCourierOrder?->available;
    }

    /**
     * Whether the parcels can only be handed over at a point (point-to-point and point-to-courier
     * services): no courier collects them.
     */
    public function isDropOffOnly(Carrier $carrier): bool
    {
        return $this->getSenderPointRequirement($carrier) === PointRequirementEnum::Required;
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
        $offered = $carrier->additionalData?->courierService;
        if ($offered === null) {
            return $services;
        }

        $map = [
            [$offered->returnOfDocuments, AdditionalServiceEnum::ReturnOfDocuments],
            [$offered->coverSenderAddress, AdditionalServiceEnum::CoverSenderAddress],
            [$offered->carryIn, AdditionalServiceEnum::CarryIn],
            [$offered->courierBringsLabel, AdditionalServiceEnum::CourierBringsLabel],
            [$offered->saturdayDelivery, AdditionalServiceEnum::SaturdayDelivery],
            [$offered->phoneCallBeforeDelivery, AdditionalServiceEnum::PhoneCallBeforeDelivery],
            [$offered->phoneCallBeforeDelivery, AdditionalServiceEnum::PhoneCallBeforePickup],
            [$offered->tires, AdditionalServiceEnum::Tires],
            [$offered->handDelivery, AdditionalServiceEnum::HandDelivery],
            [$offered->weekendDelivery, AdditionalServiceEnum::WeekendDelivery],
            [$offered->labelless, AdditionalServiceEnum::Labelless],
            [$offered->handleWithCare, AdditionalServiceEnum::HandleWithCare],
            [$offered->deliveryByTime, AdditionalServiceEnum::DeliveryByTime],
        ];
        foreach ($map as [$property, $service]) {
            if ($property instanceof Property && $property->available) {
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
            $carrier->additionalData->additionalFields ?? [],
            static fn(AdditionalField $field): bool => $field->enabled,
        ));
    }
}
