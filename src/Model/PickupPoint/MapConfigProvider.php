<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\PickupPoint;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Api\Data\QuotePointInterface;
use Polkurier\Shipping\Api\QuotePointRepositoryInterface;
use Polkurier\Shipping\Controller\Map\Token;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Webservice\Enum\Courier\PointFunctionEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Tells the checkout which Polkurier methods deliver to a pickup point, and how to open the map.
 *
 * The map token comes with it when there is one, along with when it stops working; the page asks
 * tokenUrl for a fresh one when it has none or it's about to run out. Never breaks checkout: if
 * Polkurier cannot be asked, no method offers the map, and the server refuses an order that needed
 * a point without one.
 */
readonly class MapConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private ConfigProvider $configProvider,
        private CarrierConfigProvider $carrierConfigProvider,
        private CarrierCatalog $carrierCatalog,
        private CarrierFeatureResolver $carrierFeatureResolver,
        private MapTokenProvider $mapTokenProvider,
        private QuotePointRepositoryInterface $quotePointRepository,
        private Session $checkoutSession,
        private StoreManagerInterface $storeManager,
        private UrlInterface $url,
        private Logger $logger,
    ) {}

    /**
     * @return array{pickupPoints: array<string, mixed>}
     */
    public function getConfig(): array
    {
        $quoteId = (int)$this->checkoutSession->getQuoteId();

        return [
            'pickupPoints' => $this->getMapConfig($quoteId ? $this->quotePointRepository->getForQuote($quoteId) : null),
        ];
    }

    /**
     * What the storefront's point picker needs, for the regular checkout and each address of the
     * multi-address one alike.
     *
     * @param QuotePointInterface|null $point the point already chosen, if any
     *
     * @return array<string, mixed>
     */
    public function getMapConfig(?QuotePointInterface $point): array
    {
        $store = $this->storeManager->getStore();
        $storeId = (int)$store->getId();
        $methods = [];
        $token = ['token' => '', 'expires' => 0];

        if ($this->configProvider->isActive($storeId)) {
            try {
                foreach ($this->carrierConfigProvider->getActive() as $code => $carrierConfig) {
                    $carrier = $this->carrierCatalog->getCarrier($code);
                    if ($carrier !== null && $this->carrierFeatureResolver->isRecipientPointAllowed($carrier)) {
                        $methods[$code] = [
                            'providers' => $this->carrierFeatureResolver->getPointProviders($carrier),
                            'required' => $this->carrierFeatureResolver->isRecipientPointRequired($carrier),
                            'cod' => $this->carrierFeatureResolver->isCodAvailable($carrier),
                        ];
                    }
                }
            } catch (PolkurierException $e) {
                $this->logger->warning('Pickup point map unavailable in checkout', ['exception' => $e]);
                $methods = [];
            }
            if ($methods !== []) {
                // Without a token now, the page asks for one when the map is opened; methods that
                // need a point are only offered while there's a map
                // ({@see \Polkurier\Shipping\Model\Carrier\RateCollector}).
                $token = $this->mapTokenProvider->getTokenWithExpiry();
            }
        }

        return [
            'methods' => (object)$methods,
            'token' => $token['token'],
            'tokenExpires' => $token['expires'],
            'tokenUrl' => $this->url->getUrl(Token::ROUTE),
            'baseUrl' => $this->configProvider->getMapUrl() . '/',
            'latitude' => $this->configProvider->getMapLatitude($storeId),
            'longitude' => $this->configProvider->getMapLongitude($storeId),
            'zoom' => $this->configProvider->getMapZoom($storeId),
            'showList' => $this->configProvider->isMapShowList($storeId),
            'showSearch' => $this->configProvider->isMapShowSearch($storeId),
            'functions' => \array_map(
                static fn(PointFunctionEnum $function): string => $function->value,
                $this->configProvider->getMapPointFunctions($storeId),
            ),
            'selected' => $point === null ? null : [
                'method' => $point->getMethodCode(),
                'id' => $point->getPointId(),
                'label' => (string)$point->getPointLabel(),
                'cod' => $point->getPointCod(),
            ],
            'carrierCode' => Polkurier::CODE,
        ];
    }
}
