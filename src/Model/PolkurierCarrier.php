<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Directory\Helper\Data;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Simplexml\ElementFactory;
use Magento\Shipping\Model\Tracking\Result\ErrorFactory as TrackingErrorFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackingResultFactory;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Shipment\PriceCalculatorInterface;
use Polkurier\Shipping\Model\Provider\ConfigProvider;
use Polkurier\Webservice\Model\Entity\Carrier;
use Psr\Log\LoggerInterface;

class PolkurierCarrier extends AbstractCarrierOnline implements CarrierInterface
{
    public const string CODE = 'polkurier';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @param CarrierConfigRepositoryInterface $carrierConfigRepository
     * @param PriceCalculatorInterface $priceCalculator
     * @param ConfigProvider $configProvider
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param Security $xmlSecurity
     * @param ElementFactory $xmlElFactory
     * @param ResultFactory $rateFactory
     * @param MethodFactory $rateMethodFactory
     * @param TrackingResultFactory $trackFactory
     * @param TrackingErrorFactory $trackErrorFactory
     * @param StatusFactory $trackStatusFactory
     * @param RegionFactory $regionFactory
     * @param CountryFactory $countryFactory
     * @param CurrencyFactory $currencyFactory
     * @param Data $directoryData
     * @param StockRegistryInterface $stockRegistry
     * @param array $data
     */
    public function __construct(
        /**
         * CarrierConfigRepositoryInterface
         */
        private readonly CarrierConfigRepositoryInterface $carrierConfigRepository,

        /**
         * @var PriceCalculatorInterface
         */
        private readonly PriceCalculatorInterface $priceCalculator,

        /**
         * @var ConfigProvider
         */
        private readonly ConfigProvider $configProvider,

        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        Security $xmlSecurity,
        ElementFactory $xmlElFactory,
        ResultFactory $rateFactory,
        MethodFactory $rateMethodFactory,
        TrackingResultFactory $trackFactory,
        TrackingErrorFactory $trackErrorFactory,
        StatusFactory $trackStatusFactory,
        RegionFactory $regionFactory,
        CountryFactory $countryFactory,
        CurrencyFactory $currencyFactory,
        Data $directoryData,
        StockRegistryInterface $stockRegistry,
        array $data = []
    ) {
        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateFactory,
            $rateMethodFactory,
            $trackFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $data
        );
    }

    /**
     * @param RateRequest $request
     *
     * @return Result|bool
     */
    public function collectRates(RateRequest $request): Result|bool
    {
        if (!$this->configProvider->isEnabled()) {
            return false;
        }

        $result = $this->_rateFactory->create();
        foreach ($this->carrierConfigRepository->findActive() as $carrierConfig) {
            if ($this->isForeignShipment($request) && !$this->isForeignShipmentAllowed($carrierConfig)) {
                continue;
            }
            $method = $this->_rateMethodFactory->create();
            $method->setCarrier($this->_code);
            $method->setMethod($carrierConfig->getCarrierCode());
            $method->setMethodTitle(
                $carrierConfig->getCustomLabel() ?: $carrierConfig->getExtData()[Carrier::FIELD_NAME]
            );
            $price = $this->priceCalculator->calculatePrice($carrierConfig, $request);
            $method->setPrice($price);
            $method->setCost($price);
            $result->append($method);
        }

        return $result;
    }

    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        // TODO: Implement _doShipmentRequest() method.
    }

    /**
     * @param RateRequest $request
     *
     * @return bool
     */
    private function isForeignShipment(RateRequest $request): bool
    {
        return $request->getDestCountryId() !== $this->configProvider->getHomeCountryId();
    }

    /**
     * @param CarrierConfigInterface $carrierConfig
     *
     * @return bool
     */
    private function isForeignShipmentAllowed(CarrierConfigInterface $carrierConfig): bool
    {
        return $carrierConfig->getExtData()[Carrier::FIELD_FOREIGN_SHIPMENTS];
    }

    /**
     * @return string[]
     */
    public function getAllowedMethods(): array
    {
        $activeCarriers = $this->carrierConfigRepository->findActive();
        $result = [];
        foreach ($activeCarriers as $carrier) {
            $result[$carrier->getCarrierCode()] = $carrier->getCustomLabel() ?: $carrier->getExtData()->getName();
        }

        return $result;
    }
}
