<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Api\Data\QuotePointInterface;
use Polkurier\Shipping\Controller\Map\Token;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierConfigs;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Webservice\Enum\Courier\PointFunctionEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Psr\Log\LoggerInterface;

/**
 * Tells the checkout which Polkurier methods deliver to a pickup point, and how to open the map.
 *
 * The map's token comes with it when there is one, with when it stops working; the page asks
 * tokenUrl for a fresh one when it has none or it's about to run out. Never breaks checkout: if
 * Polkurier cannot be asked, no method offers the map, and the server refuses an order that needed
 * a point without one.
 */
readonly class MapConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private Config $config,
        private CarrierConfigs $carrierConfigs,
        private CarrierCatalog $catalog,
        private CarrierFeatures $features,
        private MapTokenProvider $tokenProvider,
        private OrderPointStore $pointStore,
        private Session $checkoutSession,
        private StoreManagerInterface $storeManager,
        private UrlInterface $url,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array{pickupPoints: array<string, mixed>}
     */
    public function getConfig(): array
    {
        $quoteId = (int)$this->checkoutSession->getQuoteId();

        return ['pickupPoints' => $this->getMapConfig($quoteId ? $this->pointStore->getForQuote($quoteId) : null)];
    }

    /**
     * What the storefront's point picker needs, for the regular checkout and each address of the
     * multi-address one alike.
     *
     * @param QuotePointInterface|null $point the point already chosen, if any
     * @return array<string, mixed>
     */
    public function getMapConfig(?QuotePointInterface $point): array
    {
        $store = $this->storeManager->getStore();
        $storeId = (int)$store->getId();
        $methods = [];
        $token = '';
        $expires = 0;

        if ($this->config->isActive($storeId)) {
            try {
                foreach ($this->carrierConfigs->getActive() as $code => $carrierConfig) {
                    $carrier = $this->catalog->getCarrier($code);
                    if ($carrier !== null && $this->features->isRecipientPointAllowed($carrier)) {
                        $methods[$code] = [
                            'providers' => $this->features->getPointProviders($carrier),
                            'required' => $this->features->isRecipientPointRequired($carrier),
                        ];
                    }
                }
            } catch (PolkurierException $e) {
                $this->logger->error('Pickup point map unavailable in checkout', ['exception' => $e]);
                $methods = [];
            }
            if ($methods !== []) {
                // Without a token now, the page asks for one when the map is opened; methods that
                // need a point are only offered while there's a map (RateCollector).
                try {
                    $token = $this->tokenProvider->getToken();
                    $expires = $this->tokenProvider->getExpiry();
                } catch (PolkurierException) {
                }
            }
        }

        // Neutral names only: nothing sent to the storefront may name Polkurier.
        return [
            'methods' => (object)$methods,
            'token' => $token,
            'tokenExpires' => $expires,
            'tokenUrl' => $this->url->getUrl(Token::ROUTE),
            'baseUrl' => $this->config->getMapUrl() . '/',
            'latitude' => $this->config->getMapLatitude($storeId),
            'longitude' => $this->config->getMapLongitude($storeId),
            'zoom' => $this->config->getMapZoom($storeId),
            'showList' => $this->config->isMapShowList($storeId),
            'showSearch' => $this->config->isMapShowSearch($storeId),
            'functions' => \array_map(
                static fn(PointFunctionEnum $function): string => $function->value,
                $this->config->getMapPointFunctions($storeId),
            ),
            'selected' => $point === null ? null : [
                'method' => $point->getMethodCode(),
                'id' => $point->getPointId(),
                'label' => (string)$point->getPointLabel(),
            ],
            'carrierCode' => Polkurier::CODE,
        ];
    }
}
