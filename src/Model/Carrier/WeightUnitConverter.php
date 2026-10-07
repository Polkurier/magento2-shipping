<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Measure\Weight;
use Magento\Shipping\Helper\Carrier as CarrierHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * Weights in the store's unit, kilograms or pounds, in kilograms, as Polkurier takes them; the
 * conversion is Magento's own, as its carriers use.
 */
readonly class WeightUnitConverter
{
    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private CarrierHelper $carrierHelper,
    ) {}

    public function toKilograms(float $weight, int|string|null $storeId): float
    {
        $configuredUnit = $this->scopeConfig->getValue(
            DirectoryHelper::XML_PATH_WEIGHT_UNIT,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
        $unit = $configuredUnit === 'lbs'
            ? Weight::LBS
            : Weight::KILOGRAM;

        return (float)$this->carrierHelper->convertMeasureWeight($weight, $unit, Weight::KILOGRAM);
    }
}
