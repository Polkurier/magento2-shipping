<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Helper;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Model\PolkurierCarrier;
use Polkurier\Webservice\Enum\PointRequirementEnum;
use Polkurier\Webservice\Model\Entity\Carrier;
use Polkurier\Webservice\Model\Entity\Carrier\AdditionalData;
use Polkurier\Webservice\Model\Entity\Carrier\AdditionalData\PointsMap;
use Polkurier\Webservice\Model\Entity\Carrier\AdditionalData\Property;

readonly class ShipmentHelper
{
    /**
     * @param CarrierConfigRepositoryInterface $carrierConfigRepository
     */
    public function __construct(
        private CarrierConfigRepositoryInterface $carrierConfigRepository,
    ) {}

    /**
     * @param string $carrierCode
     *
     * @return bool
     */
    public function isCodAvailableForCarrier(string $carrierCode): bool
    {
        $carrierConfig = $this->carrierConfigRepository->findOneByCode($carrierCode)?->getExtData();

        return $carrierConfig[Carrier::FIELD_ADDITIONAL_DATA][AdditionalData::FIELD_COD][Property::FIELD_AVAILABLE]
            ?? false;
    }

    /**
     * @param string $carrierCode
     *
     * @return bool
     */
    public function isPointIdRequired(string $carrierCode): bool
    {
        $carrierConfig = $this->carrierConfigRepository->findOneByCode($carrierCode)?->getExtData();
        $pointsMap = $carrierConfig
            ? $carrierConfig[Carrier::FIELD_ADDITIONAL_DATA][AdditionalData::FIELD_POINTS_MAP]
            : null;

        return $pointsMap && $pointsMap[PointsMap::FIELD_RECIPIENT_POINT] === PointRequirementEnum::REQUIRED;
    }

    /**
     * @param CartInterface $quote
     *
     * @return bool
     */
    public function isQuotePolkurierShipment(CartInterface $quote): bool
    {
        $carrierCode = \explode('_', (string)$quote->getShippingAddress()->getShippingMethod())[0];

        return $carrierCode === PolkurierCarrier::CODE;
    }

    /**
     * @param ShipmentInterface $shipment
     *
     * @return bool
     */
    public function isShipmentPolkurierShipment(ShipmentInterface $shipment): bool
    {
        return $shipment->getOrder()->getShippingMethod(true)->getCarrierCode() === PolkurierCarrier::CODE;
    }

    /**
     * @param OrderInterface $order
     *
     * @return bool
     */
    public function isOrderPolkurierShipment(OrderInterface $order): bool
    {
        return $order->getShippingMethod(true)->getCarrierCode() === PolkurierCarrier::CODE;
    }

    /**
     * @param OrderAddressInterface $orderAddress
     *
     * @return bool
     */
    public function isOrderAddressPolkurierShipment(OrderAddressInterface $orderAddress): bool
    {
        return $this->isOrderPolkurierShipment($orderAddress->getOrder());
    }

    /**
     * @param CartInterface $quote
     *
     * @return string
     */
    public function getCarrierCode(CartInterface $quote): string
    {
        return \preg_replace(
            '/^' . PolkurierCarrier::CODE . '_/',
            '',
            $quote->getShippingAddress()->getShippingMethod()
        );
    }
}
