<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterfaceFactory;
use Polkurier\Shipping\Enum\ConfigPath;
use Polkurier\Webservice\Api\ConfigProviderInterface;
use Polkurier\Webservice\Api\Data\ConfigInterface;
use Polkurier\Webservice\Api\Data\ConfigInterfaceFactory;
use Polkurier\Webservice\Api\Data\Http\Request\AuthorizationMessageInterfaceFactory;
use Polkurier\Webservice\Enum\PointFunctionEnum;

/**
 * Provider for the module's system configuration. Provides the configuration for polkurier-sdk use
 */
readonly class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var ConfigInterface
     */
    private ConfigInterface $config;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ConfigInterfaceFactory $configInterfaceFactory
     * @param AuthorizationMessageInterfaceFactory $authorizationMessageInterfaceFactory
     * @param ProductMetadataInterfaceFactory $productMetadataFactory
     */
    public function __construct(
        /**
         * @var ScopeConfigInterface
         */
        private ScopeConfigInterface $scopeConfig,
        ConfigInterfaceFactory $configInterfaceFactory,
        AuthorizationMessageInterfaceFactory $authorizationMessageInterfaceFactory,
        ProductMetadataInterfaceFactory $productMetadataFactory,
    ) {
        $metadata = $productMetadataFactory->create();
        $authorization = $authorizationMessageInterfaceFactory->create([
            'token' => $this->getApiToken(),
            'login' => $this->getCustomerId(),
        ]);
        $this->config = $configInterfaceFactory->create([
            'authorization' => $authorization,
            'platform' => "{$metadata->getName()} {$metadata->getEdition()}",
            'platformVersion' => $metadata->getVersion(),
            // TODO: remove after development
            'apiUrl' => 'http://api.polkurier.local',
            'sandbox' => $this->isSandboxMode(),
        ]);
    }

    /**
     * @return ConfigInterface
     */
    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(ConfigPath::ENABLED->value);
    }

    /**
     * @return bool
     */
    public function isSandboxMode(): bool
    {
        return $this->scopeConfig->isSetFlag(ConfigPath::SANDBOX_MODE->value);
    }

    /**
     * @return string|null
     */
    public function getCustomerId(): ?string
    {
        return $this->scopeConfig->getValue(ConfigPath::CUSTOMER_ID->value);
    }

    /**
     * @return string|null
     */
    public function getApiToken(): ?string
    {
        return $this->scopeConfig->getValue(ConfigPath::API_TOKEN->value);
    }

    /**
     * @return string
     */
    public function getHomeCountryId(): string
    {
        return $this->scopeConfig->getValue(ConfigPath::HOME_COUNTRY_ID->value);
    }

    /**
     * @return string[]
     */
    public function getActiveCarriers(): array
    {
        return \explode(',', (string)$this->scopeConfig->getValue(ConfigPath::AVAILABLE_CARRIERS->value));
    }

    /**
     * @return string
     */
    public function getMapBaseUrl(): string
    {
//        return $this->isSandboxMode()
//            ? 'https://maps-sandbox.polkurier.pl'
//            : 'https://maps.polkurier.pl';
        // TODO: Remove after development
        return 'http://maps.polkurier.local';
    }

    /**
     * @return float
     */
    public function getMapDefaultLatitude(): float
    {
        return (float)$this->scopeConfig->getValue(ConfigPath::MAP_DEFAULT_LATITUDE->value);
    }

    /**
     * @return float
     */
    public function getMapDefaultLongitude(): float
    {
        return (float)$this->scopeConfig->getValue(ConfigPath::MAP_DEFAULT_LONGITUDE->value);
    }

    /**
     * @return int
     */
    public function getMapDefaultZoom(): int
    {
        return (int)$this->scopeConfig->getValue(ConfigPath::MAP_DEFAULT_ZOOM->value);
    }

    /**
     * @return bool
     */
    public function isMapShowList(): bool
    {
        return $this->scopeConfig->isSetFlag(ConfigPath::MAP_SHOW_LIST->value);
    }

    /**
     * @return bool
     */
    public function isMapShowSearchInput(): bool
    {
        return $this->scopeConfig->isSetFlag(ConfigPath::MAP_SHOW_SEARCH_INPUT->value);
    }

    /**
     * @return PointFunctionEnum[]
     */
    public function getRequiredPointFunctions(): array
    {
        return \array_map(
            fn (string $pointFunction) => PointFunctionEnum::from($pointFunction),
            \explode(',', (string)$this->scopeConfig->getValue(ConfigPath::MAP_REQUIRED_POINT_FUNCTIONS->value))
        );
    }
}
