<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Helper;

use JMS\Serializer\SerializerInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterfaceFactory;
use Polkurier\Webservice\Api\Data\Entity\Carrier\AdditionalData\AdditionalFieldInterface;
use Polkurier\Webservice\Api\Data\Entity\CarrierInterface;
use Polkurier\Webservice\Enum\AdditionalServiceEnum;
use Polkurier\Webservice\Enum\ShipmentTypeEnum;
use Polkurier\Webservice\Model\SerializerProvider;

readonly class CarrierMapper
{
    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @param CarrierConfigInterfaceFactory $configFactory
     * @param SerializerProvider $serializerProvider
     */
    public function __construct(
        /**
         * @var CarrierConfigInterfaceFactory
         */
        private CarrierConfigInterfaceFactory $configFactory,

        SerializerProvider $serializerProvider,
    ) {
        $this->serializer = $serializerProvider->getSerializer();
    }

    /**
     * @param CarrierInterface $carrier
     *
     * @return CarrierConfigInterface
     */
    public function mapExtCarrier(CarrierInterface $carrier): CarrierConfigInterface
    {
        $config = $this->configFactory->create();
        $addData = $carrier->getAdditionalData();
        $pickup = $addData->getPickup();

        // TODO: why there is no $status or $required fields in JSON in the pickup section for some carriers
        try {
            $config->setCarrierCode($carrier->getServiceCode());
            $config->setCarrierName($carrier->getName());
            $config->setForeignShipment($carrier->isForeignShipments());
            $config->setCodAvailable($addData->getCOD()->isAvailable());
            $config->setCodInsuranceRequired($addData->getCOD()->isRequireInsurance());
            $config->setInsuranceAvailable($addData->getInsurance()->isAvailable());
            $config->setShipmentTypes($this->getShipmentTypes($carrier));
            $config->setPickupDateAvailable($pickup->getPickupDate()->isStatus());
            $config->setPickupDateRequired($pickup->getPickupDate()->isRequired());
            $config->setPickupTimeFromAvailable($pickup->getPickupTimeFrom()->isStatus());
            $config->setPickupTimeFromRequired($pickup->getPickupTimeFrom()->isRequired());
            $config->setPickupTimeToAvailable($pickup->getPickupTimeTo()->isStatus());
            $config->setPickupTimeToRequired($pickup->getPickupTimeTo()->isRequired());
            $config->setNoCourierOrderAvailable($pickup->getNoCourierOrder()->isAvailable());
            $config->setNoCourierOrderDefault($pickup->getNoCourierOrder()->isDefault());
            $config->setPointMapProviders($addData->getPointsMap()?->getAvailableProviders());
            $config->setPointMapSenderPoint($addData->getPointsMap()?->getSenderPoint());
            $config->setPointMapRecipientPoint($addData->getPointsMap()?->getRecipientPoint());
            $config->setAdditionalServices($this->getAdditionalServices($carrier));
            $config->setAdditionalFields($this->getAdditionalFields($carrier));
        } catch (\Throwable $e) {
        }

        return $config;
    }

    /**
     * @param CarrierInterface $carrier
     *
     * @return string[]|null
     */
    private function getShipmentTypes(CarrierInterface $carrier): ?array
    {
        $extShipmentType = $carrier->getAdditionalData()->getShipmentType();
        $shipmentTypes = [];
        if ($extShipmentType->getEnvelope()->isAvailable()) {
            $shipmentTypes[] = ShipmentTypeEnum::ENVELOPE->value;
        }
        if ($extShipmentType->getBox()->isAvailable()) {
            $shipmentTypes[] = ShipmentTypeEnum::BOX->value;
        }
        if ($extShipmentType->getPalette()->isAvailable()) {
            $shipmentTypes[] = ShipmentTypeEnum::PALETTE->value;
        }

        return $shipmentTypes ?: null;
    }

    /**
     * @param CarrierInterface $carrier
     *
     * @return string[]|null
     */
    private function getAdditionalServices(CarrierInterface $carrier): ?array
    {
        $extService = $carrier->getAdditionalData()->getCourierService();
        $additionalServices = [
            AdditionalServiceEnum::CHECK_CONTENT->value,
            AdditionalServiceEnum::SMS_NOTIFICATION_RECIPIENT->value,
            AdditionalServiceEnum::SMS_NOTIFICATION_RECIPIENT_WITH_NAME->value,
        ];
        if ($extService->getROD()?->isAvailable()) {
            $additionalServices[] = AdditionalServiceEnum::REFUND_DOCS->value;
        }
        if ($extService->getCourierWithLabel()?->isAvailable()) {
            $additionalServices[] = AdditionalServiceEnum::COURIER_WITH_LABEL->value;
        }
        if ($extService->getEndOfWeekCollection()?->isAvailable()) {
            $additionalServices[] = AdditionalServiceEnum::WEEK_COLLECTION->value;
        }
        if ($extService->getLabelless()?->isAvailable()) {
            $additionalServices[] = AdditionalServiceEnum::LABELLESS->value;
        }
        if ($extService->getTires()?->isAvailable()) {
            $additionalServices[] = AdditionalServiceEnum::TIRES->value;
        }
        if ($extService->getHandDelivery()?->isAvailable()) {
            $additionalServices[] = AdditionalServiceEnum::HAND_DELIVERY->value;
        }
        if ($extService->getDeliveryToTime()?->isAvailable()) {
            $additionalServices[] = AdditionalServiceEnum::DELIVERY_TO_TIME->value;
        }
        if ($extService->getPhoneNotificationRecipient()?->isAvailable()) {
            $additionalServices[] = AdditionalServiceEnum::PHONE_NOTIFICATION_RECIPIENT->value;
            $additionalServices[] = AdditionalServiceEnum::PHONE_NOTIFICATION_COLLECTION->value;
        }
        if ($extService->getSaturdayDelivery()?->isAvailable()) {
            $additionalServices[] = AdditionalServiceEnum::SATURDAY_DELIVERY->value;
        }
        if ($extService->getBringingDeliveredParcel()?->isAvailable()) {
            $additionalServices[] = AdditionalServiceEnum::BRINGING_DELIVERED_PARCEL->value;
        }
        if ($extService->getCoverAddressSender()?->isAvailable()) {
            $additionalServices[] = AdditionalServiceEnum::COVER_ADDRESS_SENDER->value;
        }
        if ($extService->getHandleWithCare()?->isAvailable()) {
            $additionalServices[] = AdditionalServiceEnum::HANDLE_WITH_CARE->value;
        }

        return $additionalServices ?: null;
    }

    /**
     * @param CarrierInterface $carrier
     *
     * @return array|null
     */
    private function getAdditionalFields(CarrierInterface $carrier): ?array
    {
        return !empty($carrier->getAdditionalData()->getAdditionalFields())
            ? \array_map(
                fn (AdditionalFieldInterface $additionalField) => \json_decode($this->serializer->serialize($additionalField, 'json'), true),
                $carrier->getAdditionalData()->getAdditionalFields()
            )
            : null;
    }
}
