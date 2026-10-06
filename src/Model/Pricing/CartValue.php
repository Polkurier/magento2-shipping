<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Pricing;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Store\Model\ScopeInterface;
use Polkurier\Shipping\Enum\CartValueEnum;
use Polkurier\Shipping\Model\Config;

/**
 * The cart's value after discounts, with or without tax as the merchant set, in the base currency,
 * for price tiers by subtotal.
 *
 * Rates are collected after Magento has worked out each item's row total with and without tax,
 * and its discount, but before the tax on the discounted amounts: an item's discount is taken off
 * both in proportion, from the price Magento applies discounts to.
 */
readonly class CartValue
{
    /**
     * Magento's "Apply Discount On Prices": whether discounts are worked out on prices with tax.
     */
    private const string XML_PATH_DISCOUNT_TAX = 'tax/calculation/discount_tax';

    public function __construct(
        private Config $config,
        private ScopeConfigInterface $scopeConfig,
    ) {}

    public function of(RateRequest $request): float
    {
        $storeId = (int)$request->getStoreId();
        $gross = $this->config->getTierCartValue($storeId) === CartValueEnum::Gross;
        $discountOnGross = $this->scopeConfig->isSetFlag(self::XML_PATH_DISCOUNT_TAX, ScopeInterface::SCOPE_STORE, $storeId);
        $items = \array_filter(
            (array)$request->getAllItems(),
            // A bundle's or configurable product's own row holds its children's totals and discounts.
            static fn($item): bool => $item instanceof AbstractItem && !$item->getParentItem(),
        );
        if ($items === []) {
            return (float)$request->getPackageValueWithDiscount();
        }

        $value = 0.0;
        foreach ($items as $item) {
            $net = (float)$item->getData('base_row_total');
            // Without tax worked out, as with taxes off, the row is the same both ways.
            $withTax = (float)$item->getData('base_row_total_incl_tax') ?: $net;
            $discounted = $discountOnGross ? $withTax : $net;
            $kept = $discounted > 0.0 ? 1.0 - \min(1.0, (float)$item->getData('base_discount_amount') / $discounted) : 0.0;
            $value += ($gross ? $withTax : $net) * $kept;
        }

        return \round($value, 4);
    }
}
