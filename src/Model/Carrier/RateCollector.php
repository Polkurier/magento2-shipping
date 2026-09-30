<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\Checkout\MapTokenProvider;
use Polkurier\Shipping\Model\Pricing\PriceCalculatorInterface;
use Polkurier\Shipping\Model\Pricing\RateContext;
use Polkurier\Shipping\Model\Sdk\RecentFailureException;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Response\Carrier;
use Psr\Log\LoggerInterface;

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
class RateCollector
{
    private const float KG_PER_POUND = 0.45359237;

    public function __construct(
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        private readonly CarrierConfigs $carrierConfigs,
        private readonly CarrierCatalog $catalog,
        private readonly CarrierFeatures $features,
        private readonly PriceCalculatorInterface $priceCalculator,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
        private readonly MapTokenProvider $mapTokenProvider,
        private readonly State $appState,
    ) {}

    /**
     * @return Result|false false when no carrier is set up
     */
    public function collect(RateRequest $request): Result|false
    {
        $storeId = (int)$request->getStoreId();
        $configs = $this->carrierConfigs->getActive();
        if ($configs === []) {
            return false;
        }

        $carriers = $this->getCatalog();
        $foreign = $request->getDestCountryId() && $request->getDestCountryId() !== Polkurier::HOME_COUNTRY;
        // The subtotal is in the base currency, as the prices are; weight tiers are in kilograms.
        $context = new RateContext(
            subtotal: (float)$request->getPackageValueWithDiscount(),
            weight: (float)$request->getPackageWeight() * $this->kilogramsPerUnit($storeId),
        );

        $result = $this->rateResultFactory->create();
        $mapAvailable = null;
        foreach ($configs as $code => $config) {
            $carrier = $carriers === null ? null : ($carriers[$code] ?? null);
            if ($carriers !== null && $carrier === null) {
                continue;
            }
            if ($foreign && ($carrier === null || !$this->features->isForeign($carrier))) {
                continue;
            }
            if ($carrier !== null && $this->features->isRecipientPointRequired($carrier)
                && !($mapAvailable ??= $this->canChoosePoints())
            ) {
                continue;
            }
            // A method the merchant doesn't price for this cart isn't offered, free shipping or not.
            $price = $this->priceCalculator->calculate($config, $context);
            if ($price === null) {
                continue;
            }
            $result->append($this->createMethod($config, $request->getFreeShipping() ? 0.0 : $price, $storeId));
        }

        return $result;
    }

    /**
     * @return array<string, string> method labels by carrier code
     */
    public function getAllowedMethods(int $storeId): array
    {
        $methods = [];
        foreach ($this->carrierConfigs->getActive() as $code => $config) {
            $methods[$code] = $config->getLabelForStore($storeId);
        }

        return $methods;
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
            $this->logger->error('Polkurier points map unavailable: pickup point methods left out of the rates', ['exception' => $e]);

            return false;
        }
    }

    private function kilogramsPerUnit(int $storeId): float
    {
        return $this->scopeConfig->getValue(DirectoryHelper::XML_PATH_WEIGHT_UNIT, ScopeInterface::SCOPE_STORE, $storeId) === 'lbs'
            ? self::KG_PER_POUND
            : 1.0;
    }

    /**
     * @return array<string, Carrier>|null null when the list is unavailable; domestic carriers
     *                                    are then still offered
     */
    private function getCatalog(): ?array
    {
        try {
            return $this->catalog->getCarriers();
        } catch (PolkurierException $e) {
            $this->logger->error('Polkurier carrier list unavailable for rates', ['exception' => $e]);

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
