<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Enum\Config\CartValueEnum;
use Polkurier\Shipping\Exception\RecentFailureException;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\Pricing\PriceCalculatorInterface;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Response\Carrier;

/**
 * The checkout rates of the merchant's Polkurier carriers, priced by the merchant's own rules.
 *
 * Rates never wait on the API beyond the cached carrier list, and never break checkout: when the
 * list is unavailable, domestic carriers are still offered and the failure is logged. Rates carry
 * no carrier title, so the storefront only ever shows the method label.
 *
 * A carrier that delivers to pickup points only is left out of the storefront's rates while the
 * points map can't open (no map token), since the shopper would have no way to choose the point
 * the order then needs. Orders created in the admin keep it: the point is set when shipping.
 */
readonly class RateCollector
{
    /**
     * Magento's "Apply Discount On Prices": whether discounts are worked out on prices with tax.
     */
    private const string XML_PATH_DISCOUNT_TAX = 'tax/calculation/discount_tax';

    public function __construct(
        private ResultFactory $rateResultFactory,
        private MethodFactory $rateMethodFactory,
        private CarrierConfigProvider $carrierConfigProvider,
        private CarrierCatalog $carrierCatalog,
        private CarrierFeatureResolver $carrierFeatureResolver,
        private PriceCalculatorInterface $priceCalculator,
        private WeightUnitConverter $weightUnitConverter,
        private Logger $logger,
        private MapTokenProvider $mapTokenProvider,
        private State $appState,
        private ConfigProvider $configProvider,
        private ScopeConfigInterface $scopeConfig,
    ) {}

    /**
     * @return Result|false false when no carrier is set up
     */
    public function collect(RateRequest $request): Result|false
    {
        $storeId = (int)$request->getStoreId();
        $configs = $this->carrierConfigProvider->getActive();
        if ($configs === []) {
            return false;
        }

        $carriers = $this->getCatalog();
        $foreign = $request->getDestCountryId() && $request->getDestCountryId() !== Polkurier::HOME_COUNTRY;
        // The subtotal is in the base currency, as the prices are; weight tiers are in kilograms.
        $subtotal = $this->getCartValue($request);
        $weight = $this->weightUnitConverter->convertToKilograms((float)$request->getPackageWeight(), $storeId);

        $result = $this->rateResultFactory->create();
        $mapAvailable = null;
        foreach ($configs as $code => $config) {
            if ($carriers !== null && !isset($carriers[$code])) {
                continue;
            }
            $carrier = $carriers[$code] ?? null;
            // Carriers for abroad ship only abroad, the others only within Poland: Polkurier prices
            // neither the other way. Without the list, a carrier is assumed to be domestic.
            if ($foreign !== (bool)$carrier?->isForeignShipments()) {
                continue;
            }
            if ($carrier !== null && $this->carrierFeatureResolver->isRecipientPointRequired($carrier)
                && !($mapAvailable ??= $this->canChoosePoints())
            ) {
                continue;
            }
            // A method the merchant doesn't price for this cart isn't offered, free shipping or not.
            $price = $this->priceCalculator->calculate($config, $subtotal, $weight);
            if ($price === null) {
                continue;
            }
            $result->append($this->createMethod($config, $request->getFreeShipping() ? 0.0 : $price, $storeId));
        }

        return $result;
    }

    /**
     * The cart value that subtotal price tiers compare: after discounts, with or without tax as the
     * merchant set, in the base currency.
     *
     * Rates are collected after Magento has worked out each item's row total with and without
     * tax, and its discount, but before the tax on the discounted amounts: an item's discount is
     * taken off both in proportion, from the price Magento applies discounts to.
     */
    public function getCartValue(RateRequest $request): float
    {
        $storeId = (int)$request->getStoreId();
        $gross = $this->configProvider->getTierCartValue($storeId) === CartValueEnum::Gross;
        $discountOnGross = $this->scopeConfig->isSetFlag(
            self::XML_PATH_DISCOUNT_TAX,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
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
            // With no tax worked out (e.g. taxes off), the row total is the same either way.
            $withTax = (float)$item->getData('base_row_total_incl_tax') ?: $net;
            $discounted = $discountOnGross ? $withTax : $net;
            $kept = $discounted > 0.0
                ? 1.0 - \min(1.0, (float)$item->getData('base_discount_amount') / $discounted)
                : 0.0;
            $value += ($gross ? $withTax : $net) * $kept;
        }

        return \round($value, 4);
    }

    /**
     * Whether the shopper can choose a pickup point: the map opens with a token. Always in the
     * admin, where the merchant sets the point later.
     */
    private function canChoosePoints(): bool
    {
        try {
            if ($this->appState->getAreaCode() === Area::AREA_ADMINHTML) {
                return true;
            }
        } catch (LocalizedException) {
        }
        try {
            return $this->mapTokenProvider->getToken() !== '';
        } catch (RecentFailureException) {
            return false;
        } catch (PolkurierException $e) {
            $this->logger->warning(
                'Polkurier points map unavailable: pickup point methods left out of the rates',
                ['exception' => $e],
            );

            return false;
        }
    }

    /**
     * @return array<string, Carrier>|null null when the list is unavailable; domestic carriers
     *                                    are then still offered
     */
    private function getCatalog(): ?array
    {
        try {
            return $this->carrierCatalog->getCarriers();
        } catch (PolkurierException $e) {
            $this->logger->warning('Polkurier carrier list unavailable for rates', ['exception' => $e]);

            return null;
        }
    }

    private function createMethod(CarrierConfigInterface $config, float $price, int $storeId): Method
    {
        $method = $this->rateMethodFactory->create();
        $method->setCarrier(Polkurier::CODE);
        $method->setCarrierTitle('');
        $method->setMethod($config->getCarrierCode());
        $method->setMethodTitle($config->getLabelForStore($storeId));
        $method->setPrice($price);
        $method->setCost($price);

        return $method;
    }
}
