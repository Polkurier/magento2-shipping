<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Helper;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Model\PolkurierCarrier;
use Polkurier\Webservice\Model\Entity\Carrier;
use Polkurier\Webservice\Model\Entity\Carrier\AdditionalData;
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
        $carrierCode = \explode('_', (string)$shipment->getOrder()->getShippingMethod())[0];

        return $carrierCode === PolkurierCarrier::CODE;
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
