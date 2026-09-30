<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Api\Data\QuotePointInterface;
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
 * Never breaks checkout: if Polkurier cannot be asked, no method offers the map, and the server
 * refuses an order that needed a point without one.
 */
class MapConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly CarrierConfigs $carrierConfigs,
        private readonly CarrierCatalog $catalog,
        private readonly CarrierFeatures $features,
        private readonly MapTokenProvider $tokenProvider,
        private readonly OrderPointStore $pointStore,
        private readonly Session $checkoutSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
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
                if ($methods !== []) {
                    $token = $this->tokenProvider->getToken();
                }
                if ($token === '' && $methods !== []) {
                    // Without a token there is no map to choose from, so no method may ask for a point.
                    $this->logger->error('Pickup point map unavailable in checkout: no map token');
                    $methods = [];
                }
            } catch (PolkurierException $e) {
                $this->logger->error('Pickup point map unavailable in checkout', ['exception' => $e]);
                $methods = [];
            }
        }

        // Neutral names only: nothing sent to the storefront may name Polkurier.
        return [
            'methods' => (object)$methods,
            'token' => $token,
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
