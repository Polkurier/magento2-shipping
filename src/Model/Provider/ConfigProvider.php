<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Provider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterfaceFactory;
use Polkurier\Shipping\Enum\AdditionalServiceExtensionEnum;
use Polkurier\Shipping\Enum\ConfigPathEnum;
use Polkurier\Webservice\Api\ConfigProviderInterface;
use Polkurier\Webservice\Api\Data\ConfigInterface;
use Polkurier\Webservice\Api\Data\ConfigInterfaceFactory;
use Polkurier\Webservice\Api\Data\Http\Request\AuthorizationMessageInterfaceFactory;
use Polkurier\Webservice\Enum\CodReturnTypeEnum;
use Polkurier\Webservice\Enum\CodTypeEnum;
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
        return $this->scopeConfig->isSetFlag(ConfigPathEnum::ACTIVE->value);
    }

    /**
     * @return bool
     */
    public function isSandboxMode(): bool
    {
        return $this->scopeConfig->isSetFlag(ConfigPathEnum::GENERAL_SANDBOX_MODE->value);
    }

    /**
     * @return string|null
     */
    public function getCustomerId(): ?string
    {
        return $this->scopeConfig->getValue(ConfigPathEnum::GENERAL_CUSTOMER_ID->value);
    }

    /**
     * @return string|null
     */
    public function getApiToken(): ?string
    {
        return $this->scopeConfig->getValue(ConfigPathEnum::GENERAL_API_TOKEN->value);
    }

    /**
     * @return string
     */
    public function getHomeCountryId(): string
    {
        return $this->scopeConfig->getValue(ConfigPathEnum::SHIPMENTS_HOME_COUNTRY_ID->value);
    }

    /**
     * @return string[]
     */
    public function getActiveCarriers(): array
    {
        return \explode(',', (string)$this->scopeConfig->getValue(ConfigPathEnum::SHIPMENTS_AVAILABLE_CARRIERS->value));
    }

    /**
     * @return bool
     */
    public function isUseStoreInformationAddress(): bool
    {
        return $this->scopeConfig->isSetFlag(ConfigPathEnum::SHIPMENTS_USE_STORE_INFORMATION_ADDRESS->value);
    }

    /**
     * @return bool
     */
    public function isUseStoreOriginAddress(): bool
    {
        return $this->scopeConfig->isSetFlag(ConfigPathEnum::SHIPMENTS_USE_STORE_ORIGIN_ADDRESS->value);
    }

    /**
     * @return string
     */
    public function getDefaultSenderEmail(): string
    {
        return $this->scopeConfig->getValue(ConfigPathEnum::SHIPMENTS_DEFAULT_SENDER_EMAIL->value);
    }

    /**
     * @return string
     */
    public function getDefaultCarrier(): string
    {
        return $this->scopeConfig->getValue(ConfigPathEnum::SHIPMENTS_DEFAULT_CARRIER->value);
    }

    /**
     * @return AdditionalServiceExtensionEnum[]
     */
    public function getDefaultAdditionalServices(): array
    {
        return \explode(
            ',',
            $this->scopeConfig->getValue(ConfigPathEnum::SHIPMENTS_DEFAULT_ADDITIONAL_SERVICES->value)
        );
    }

    /**
     * @return CodTypeEnum
     */
    public function getDefaultCodType(): CodTypeEnum
    {
        return CodTypeEnum::from($this->scopeConfig->getValue(ConfigPathEnum::SHIPMENTS_DEFAULT_COD_TYPE->value));
    }

    /**
     * @return CodReturnTypeEnum
     */
    public function getDefaultCodRefundType(): CodReturnTypeEnum
    {
        return CodReturnTypeEnum::from(
            $this->scopeConfig->getValue(ConfigPathEnum::SHIPMENTS_DEFAULT_COD_REFUND_TYPE->value)
        );
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
        return (float)$this->scopeConfig->getValue(ConfigPathEnum::MAP_DEFAULT_LATITUDE->value);
    }

    /**
     * @return float
     */
    public function getMapDefaultLongitude(): float
    {
        return (float)$this->scopeConfig->getValue(ConfigPathEnum::MAP_DEFAULT_LONGITUDE->value);
    }

    /**
     * @return int
     */
    public function getMapDefaultZoom(): int
    {
        return (int)$this->scopeConfig->getValue(ConfigPathEnum::MAP_DEFAULT_ZOOM->value);
    }

    /**
     * @return bool
     */
    public function isMapShowList(): bool
    {
        return $this->scopeConfig->isSetFlag(ConfigPathEnum::MAP_SHOW_LIST->value);
    }

    /**
     * @return bool
     */
    public function isMapShowSearchInput(): bool
    {
        return $this->scopeConfig->isSetFlag(ConfigPathEnum::MAP_SHOW_SEARCH_INPUT->value);
    }

    /**
     * @return PointFunctionEnum[]
     */
    public function getRequiredPointFunctions(): array
    {
        return \array_map(
            fn (string $pointFunction) => PointFunctionEnum::from($pointFunction),
            \explode(',', (string)$this->scopeConfig->getValue(ConfigPathEnum::MAP_REQUIRED_POINT_FUNCTIONS->value))
        );
    }
}
