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
        return (bool)$this->carrierConfigRepository->findOneByCode($carrierCode)?->isCodAvailable();
    }

    /**
     * @param string $carrierCode
     *
     * @return bool
     */
    public function isPointIdRequired(string $carrierCode): bool
    {
        return $this->carrierConfigRepository->findOneByCode($carrierCode)?->getPointMapRecipientPoint()
            === PointRequirementEnum::REQUIRED;
    }

    /**
     * @param string $carrierCode
     *
     * @return bool
     */
    public function isInsuranceRequired(string $carrierCode): bool
    {
        return (bool)$this->carrierConfigRepository->findOneByCode($carrierCode)?->isCodInsuranceRequired();
    }

    /**
     * @param CartInterface $quote
     *
     * @return bool
     */
    public function isQuotePolkurierShipment(CartInterface $quote): bool
    {
        return \explode('_', (string)$quote->getShippingAddress()->getShippingMethod())[0]
            === PolkurierCarrier::CODE;
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
