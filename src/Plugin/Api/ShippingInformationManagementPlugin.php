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
use Magento\Quote\Api\Data\CartExtensionFactory;
use Polkurier\Shipping\Api\Data\Quote\ShipmentDataInterfaceFactory;
use Polkurier\Shipping\Exception\ValidationException;
use Polkurier\Shipping\Helper\ShipmentHelper;
use Polkurier\Shipping\Model\PolkurierCarrier;

readonly class ShippingInformationManagementPlugin
{
    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param CartExtensionFactory $extensionFactory
     * @param ShipmentDataInterfaceFactory $shipmentDataFactory
     * @param ShipmentHelper $shipmentHelper
     */
    public function __construct(
        /**
         * @var CartRepositoryInterface
         */
        private CartRepositoryInterface $quoteRepository,

        /**
         * @var CartExtensionFactory
         */
        private CartExtensionFactory $extensionFactory,

        /**
         * @var ShipmentDataInterfaceFactory
         */
        private ShipmentDataInterfaceFactory $shipmentDataFactory,

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
        if ($shippingInformation->getShippingCarrierCode() !== PolkurierCarrier::CODE) {
            return [$quoteId, $shippingInformation];
        }

        $carrierCode = $shippingInformation->getShippingMethodCode();
        if (!$shippingInformation->getExtensionAttributes()?->getPolkurierPointId()
            && $this->shipmentHelper->isPointIdRequired($carrierCode)
        ) {
            throw new ValidationException(\__('No pickup point selected for shipping method "$1"', $carrierCode));
        }

        $quote = $this->quoteRepository->getActive($quoteId);
        $extensionAttributes = $quote->getExtensionAttributes() ?? $this->extensionFactory->create();
        $shipmentData = $extensionAttributes->getPolkurierShipmentData() ?? $this->shipmentDataFactory->create();
        $shipmentData->setQuoteId($quoteId);
        $shipmentData->setCarrierCode($carrierCode);
        $shipmentData->setPointId($shippingInformation->getExtensionAttributes()->getPolkurierPointId());
        $shipmentData->setCodAvailable($shippingInformation->getExtensionAttributes()->getPolkurierPointCod());
        $extensionAttributes->setPolkurierShipmentData($shipmentData);
        $quote->setExtensionAttributes($extensionAttributes);

        return [$quoteId, $shippingInformation];
    }
}
