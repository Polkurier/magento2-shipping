<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * The store's weight unit, kilograms or pounds, as Polkurier takes kilograms.
 */
readonly class StoreWeightUnit
{
    public const float KG_PER_POUND = 0.45359237;

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
    ) {}

    /**
     * How many kilograms one unit of the store's weights is.
     */
    public function kilogramsPerUnit(int|string|null $storeId): float
    {
        return $this->scopeConfig->getValue(DirectoryHelper::XML_PATH_WEIGHT_UNIT, ScopeInterface::SCOPE_STORE, $storeId) === 'lbs'
            ? self::KG_PER_POUND
            : 1.0;
    }
}
