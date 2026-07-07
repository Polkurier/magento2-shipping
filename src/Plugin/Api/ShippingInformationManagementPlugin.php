<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Api;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Polkurier\Shipping\Exception\ValidationException;
use Polkurier\Shipping\Helper\ShipmentHelper;

readonly class ShippingInformationManagementPlugin
{
    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param ShipmentHelper $shipmentHelper
     */
    public function __construct(
        /**
         * @var CartRepositoryInterface
         */
        private CartRepositoryInterface $quoteRepository,

        /**
         * @var ShipmentHelper
         */
        private ShipmentHelper $shipmentHelper,
    ) {}

    /**
     * @param ShippingInformationManagementInterface $subject
     * @param int $quoteId
     * @param ShippingInformationInterface $shippingInformation
     *
     * @return array
     * @throws NoSuchEntityException
     * @throws ValidationException
     */
    public function beforeSaveAddressInformation(
        ShippingInformationManagementInterface $subject,
        int $quoteId,
        ShippingInformationInterface $shippingInformation
    ): array {
        $quote = $this->quoteRepository->getActive($quoteId);

        if (!$this->shipmentHelper->isQuotePolkurierShipment($quote)
            || !$shippingInformation->getExtensionAttributes()->getPolkurierPointId()
            || !($shipmentData = $quote->getExtensionAttributes()->getPolkurierShipmentData())
        ) {
            return [$quoteId, $shippingInformation];
        }
        $carrierCode = $this->shipmentHelper->getCarrierCode($quote);
        if (!$shippingInformation->getExtensionAttributes()?->getPolkurierPointId()
            && $this->shipmentHelper->isPointIdRequired($carrierCode)
        ) {
            throw new ValidationException(\__('No pickup point selected for shipping method "$1"', $carrierCode));
        }

        $shipmentData->setCarrierCode($carrierCode);
        $shipmentData->setPointId($shippingInformation->getExtensionAttributes()->getPolkurierPointId());
        $shipmentData->setCodAvailable($shippingInformation->getExtensionAttributes()->getPolkurierPointCod());

        return [$quoteId, $shippingInformation];
    }
}
