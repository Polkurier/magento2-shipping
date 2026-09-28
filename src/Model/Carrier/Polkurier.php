<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Tracking\Result\Status;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Pricing\PriceCalculatorInterface;
use Polkurier\Shipping\Model\Pricing\RateContext;
use Polkurier\Shipping\Model\Shipment\NativeLabel;
use Polkurier\Shipping\Model\Shipment\TrackingLookup;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Response\Carrier;
use Psr\Log\LoggerInterface;

/**
 * Offers the merchant's Polkurier carriers in checkout, priced by the merchant's own rules.
 *
 * The name "Polkurier" never reaches the storefront: rates carry no carrier title, so Magento's
 * shipping description is the method label alone, and tracking is titled by that label too.
 *
 * Rates never wait on the API beyond the cached carrier list, and never break checkout: a
 * failure hides the methods and is logged.
 *
 * In the admin it also creates shipping labels through Magento's packaging popup; NativeLabel does
 * the work.
 */
class Polkurier extends AbstractCarrier implements CarrierInterface
{
    public const string CODE = 'polkurier';

    /**
     * The Polkurier carrier code in a Magento shipping method such as "polkurier_DPD", or null for
     * methods of other carriers.
     */
    public static function methodCodeOf(?string $shippingMethod): ?string
    {
        $prefix = self::CODE . '_';

        return $shippingMethod !== null && \str_starts_with($shippingMethod, $prefix)
            ? \substr($shippingMethod, \strlen($prefix))
            : null;
    }
    public const string HOME_COUNTRY = 'PL';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        private readonly StatusFactory $trackStatusFactory,
        private readonly CarrierConfigs $carrierConfigs,
        private readonly CarrierCatalog $catalog,
        private readonly CarrierFeatures $features,
        private readonly PriceCalculatorInterface $priceCalculator,
        private readonly TrackingLookup $trackingLookup,
        private readonly StoreManagerInterface $storeManager,
        private readonly NativeLabel $nativeLabel,
        array $data = [],
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @param RateRequest $request
     * @return Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $storeId = (int)$request->getStoreId();
        $websiteId = (int)($request->getWebsiteId() ?: $this->storeManager->getStore($storeId)->getWebsiteId());
        $configs = $this->carrierConfigs->getActive($websiteId);
        if ($configs === []) {
            return false;
        }

        $carriers = $this->getCatalog($storeId);
        $foreign = $request->getDestCountryId() && $request->getDestCountryId() !== self::HOME_COUNTRY;
        $context = new RateContext(
            subtotal: (float)$request->getPackageValueWithDiscount(),
            weight: (float)$request->getPackageWeight(),
        );

        $result = $this->rateResultFactory->create();
        foreach ($configs as $code => $config) {
            $carrier = $carriers === null ? null : ($carriers[$code] ?? null);
            if ($carriers !== null && $carrier === null) {
                continue;
            }
            if ($foreign && ($carrier === null || !$this->features->isForeign($carrier))) {
                continue;
            }
            $price = $request->getFreeShipping() ? 0.0 : $this->priceCalculator->calculate($config, $context);
            if ($price === null) {
                continue;
            }
            $result->append($this->createMethod($config, $price, $storeId));
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function getAllowedMethods(): array
    {
        $methods = [];
        $store = $this->storeManager->getStore($this->getStore());
        foreach ($this->carrierConfigs->getActive((int)$store->getWebsiteId()) as $code => $config) {
            $methods[$code] = $config->getLabelForStore((int)$store->getId());
        }

        return $methods;
    }

    public function isTrackingAvailable(): bool
    {
        return true;
    }

    public function isShippingLabelsAvailable(): bool
    {
        $store = $this->getStore();

        return $this->nativeLabel->isAvailable($store === null || $store === '' ? null : (int)$store);
    }

    /**
     * @param \Magento\Framework\DataObject $request
     * @return \Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function requestToShipment($request)
    {
        if ($request->getStoreId() !== null) {
            $this->setStore($request->getStoreId());
        }

        return $this->nativeLabel->request($request);
    }

    /**
     * @return array<string, string>
     */
    public function getContainerTypes(?\Magento\Framework\DataObject $params = null)
    {
        return $this->nativeLabel->getContainerTypes();
    }

    /**
     * @return string[]
     */
    public function getCustomizableContainerTypes()
    {
        return $this->nativeLabel->getCustomizableContainerTypes();
    }

    /**
     * Magento's packaging popup asks; sizes are length, width and height only.
     *
     * @param string|null $countyDest
     */
    public function isGirthAllowed($countyDest = null): bool
    {
        return false;
    }

    /**
     * @param string $tracking the waybill number
     * @return Status
     */
    public function getTrackingInfo($tracking)
    {
        $status = $this->trackStatusFactory->create();
        $status->setCarrier(self::CODE);
        $shipment = $this->trackingLookup->findByWaybill((string)$tracking);
        $status->setCarrierTitle($shipment !== null ? $this->getMethodLabel($shipment) : '');
        $status->setTracking($tracking);
        $status->setPopup(1);
        $status->setUrl((string)$shipment?->getTrackingUrl());

        return $status;
    }

    private function getMethodLabel(ShipmentInterface $shipment): string
    {
        $websiteId = (int)$this->storeManager->getStore((int)$shipment->getStoreId())->getWebsiteId();
        $config = $this->carrierConfigs->get($websiteId, $shipment->getCarrierCode());

        return $config !== null ? $config->getLabelForStore((int)$shipment->getStoreId()) : $shipment->getCarrierCode();
    }

    /**
     * @return array<string, Carrier>|null null when the list is unavailable; domestic carriers
     *                                    are then still offered
     */
    private function getCatalog(int $storeId): ?array
    {
        try {
            return $this->catalog->getCarriers($storeId);
        } catch (PolkurierException $e) {
            $this->_logger->error('Polkurier carrier list unavailable for rates', ['exception' => $e]);

            return null;
        }
    }

    private function createMethod(CarrierConfigInterface $config, float $price, int $storeId): \Magento\Quote\Model\Quote\Address\RateResult\Method
    {
        $method = $this->rateMethodFactory->create();
        $method->setCarrier(self::CODE);
        $method->setCarrierTitle('');
        $method->setMethod($config->getCarrierCode());
        $method->setMethodTitle($config->getLabelForStore($storeId));
        $method->setPrice($price);
        $method->setCost($price);

        return $method;
    }
}
