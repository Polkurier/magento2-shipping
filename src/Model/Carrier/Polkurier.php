<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Sales\Model\Order;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Tracking\Result\Status;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Psr\Log\LoggerInterface;

/**
 * Magento's carrier for the merchant's Polkurier carriers: an adapter over the module's services.
 * RateCollector prices the checkout rates, ShipmentFinder finds the shipment of a waybill for
 * tracking, and in the admin NativeLabelService creates shipping labels through Magento's packaging popup.
 *
 * The name "Polkurier" never reaches the storefront: rates carry no carrier title, so Magento's
 * shipping description is the method label alone, and tracking is titled by that label too.
 */
class Polkurier extends AbstractCarrier implements CarrierInterface
{
    public const string CODE = 'polkurier';

    /**
     * Where Polkurier ships from. Its carriers say whether they take "foreign shipments", which
     * means shipments to anywhere but Poland, whatever the store's own country.
     */
    public const string HOME_COUNTRY = 'PL';

    /**
     * What the carrier's shipping methods start with, before the account carrier's code.
     */
    public const string METHOD_PREFIX = self::CODE . '_';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * The Polkurier carrier code in a Magento shipping method such as "polkurier_DPD", or null for
     * methods of other carriers.
     */
    public static function methodCodeOf(?string $shippingMethod): ?string
    {
        return $shippingMethod !== null && \str_starts_with($shippingMethod, self::METHOD_PREFIX)
            ? \substr($shippingMethod, \strlen(self::METHOD_PREFIX))
            : null;
    }

    /**
     * The shipping method of one of the account's carriers, e.g. polkurier_DPD.
     */
    public static function methodOf(string $carrierCode): string
    {
        return self::METHOD_PREFIX . $carrierCode;
    }

    /**
     * Whether the order was placed with one of this carrier's methods. The module ships only
     * those; orders of other carriers are left to their own integrations.
     */
    public static function isOrderOf(Order $order): bool
    {
        return self::methodCodeOf((string)$order->getShippingMethod()) !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly RateCollector $rateCollector,
        private readonly StatusFactory $trackStatusFactory,
        private readonly ShipmentFinder $finder,
        private readonly CarrierConfigProvider $carrierConfigProvider,
        private readonly StoreManagerInterface $storeManager,
        private readonly NativeLabelService $nativeLabelService,
        private readonly PackType $packTypes,
        array $data = [],
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @param RateRequest $request
     *
     * @return Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        return $this->rateCollector->collect($request);
    }

    /**
     * Magento titles the "not available" row it shows for a country outside Ship to Specific
     * Countries with the carrier's title, which customers must not see: the row goes without one.
     *
     * @param DataObject $request
     *
     * @return $this|bool|Error
     */
    public function checkAvailableShipCountries(DataObject $request)
    {
        $result = parent::checkAvailableShipCountries($request);
        if ($result instanceof Error) {
            $result->setCarrierTitle('');
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function getAllowedMethods(): array
    {
        $store = $this->storeManager->getStore($this->getStore());

        return $this->rateCollector->getAllowedMethods((int)$store->getId());
    }

    public function isTrackingAvailable(): bool
    {
        return true;
    }

    public function isShippingLabelsAvailable(): bool
    {
        $store = $this->getStore();

        return $this->nativeLabelService->isAvailable($store === null || $store === '' ? null : (int)$store);
    }

    /**
     * @param \Magento\Framework\DataObject $request
     *
     * @return \Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function requestToShipment($request)
    {
        if ($request->getStoreId() !== null) {
            $this->setStore($request->getStoreId());
        }

        return $this->nativeLabelService->request($request);
    }

    /**
     * The parcel types, as the packaging window's package types. Pack templates are offered
     * separately, next to them, and only fill in a package.
     *
     * @return array<string, string>
     */
    public function getContainerTypes(?\Magento\Framework\DataObject $params = null)
    {
        return $this->packTypes->toArray();
    }

    /**
     * Every parcel type takes the size entered in the packaging window.
     *
     * @return string[]
     */
    public function getCustomizableContainerTypes()
    {
        return \array_map('strval', \array_keys($this->packTypes->toArray()));
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
     *
     * @return Status
     */
    public function getTrackingInfo($tracking)
    {
        $status = $this->trackStatusFactory->create();
        $status->setCarrier(self::CODE);
        $shipment = $this->finder->latestByWaybill((string)$tracking);
        // The label of the carrier it went with, in the store it was ordered in.
        $status->setCarrierTitle($shipment !== null
            ? $this->carrierConfigProvider->getLabel($shipment->getCarrierCode(), (int)$shipment->getStoreId())
                ?? $shipment->getCarrierCode()
            : '');
        $status->setTracking($tracking);
        $status->setPopup(1);
        $status->setUrl((string)$shipment?->getTrackingUrl());

        return $status;
    }
}
