<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Quote;

use Magento\Quote\Api\Data\ShippingMethodExtensionFactory;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Model\Carrier\CarrierLogoStorage;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;

/**
 * Adds the carrier's logo to each of the module's shipping methods, as the "method_logo"
 * extension attribute, for checkout to show next to the method name.
 */
readonly class ShippingMethodLogoPlugin
{
    public function __construct(
        private ShippingMethodExtensionFactory $extensionFactory,
        private CarrierConfigProvider $carrierConfigProvider,
        private CarrierLogoStorage $carrierLogoStorage,
        private StoreManagerInterface $storeManager,
    ) {}

    /**
     * @param \Magento\Quote\Model\Quote\Address\Rate $rateModel
     * @param string $quoteCurrencyCode
     */
    public function afterModelToDataObject(
        ShippingMethodConverter $subject,
        ShippingMethodInterface $result,
        $rateModel,
        $quoteCurrencyCode,
    ): ShippingMethodInterface {
        if ($result->getCarrierCode() !== Polkurier::CODE || !$result->getMethodCode()) {
            return $result;
        }
        $store = $rateModel->getAddress()?->getQuote()?->getStore() ?? $this->storeManager->getStore();
        $config = $this->carrierConfigProvider->get((string)$result->getMethodCode());
        $logo = $config !== null ? $this->carrierLogoStorage->getUrlFor($config, (int)$store->getId()) : null;
        if ($logo === null) {
            return $result;
        }
        $extension = $result->getExtensionAttributes() ?? $this->extensionFactory->create();
        $extension->setMethodLogo($logo);

        return $result->setExtensionAttributes($extension);
    }
}
