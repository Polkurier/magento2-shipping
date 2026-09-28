<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\Module\PackageInfo;
use Polkurier\Shipping\Model\Config;
use Polkurier\Webservice\Api\ConfigInterface;
use Polkurier\Webservice\Api\ConfigProviderInterface;
use Polkurier\Webservice\Config as SdkConfig;
use Polkurier\Webservice\Http\Request\AuthorizationMessage;

/**
 * SDK configuration for one store, read from Magento's scoped configuration when it is asked for.
 *
 * The SDK's services read their configuration once, in their constructors, so a provider is bound
 * to a store for its whole life. {@see ClientPool} builds one per store.
 */
class StoreConfigProvider implements ConfigProviderInterface
{
    public const string PLATFORM = 'magento2';
    public const string MODULE_NAME = 'Polkurier_Shipping';

    public function __construct(
        private readonly Config $config,
        private readonly PackageInfo $packageInfo,
        private readonly ?int $storeId = null,
    ) {}

    public function getConfig(): ConfigInterface
    {
        return new SdkConfig(
            authorization: new AuthorizationMessage(
                token: $this->config->getToken($this->storeId),
                login: $this->config->getLogin($this->storeId),
            ),
            platform: self::PLATFORM,
            platformVersion: (string)($this->packageInfo->getVersion(self::MODULE_NAME) ?: '1.0.0'),
            apiUrl: $this->config->getApiUrl($this->storeId),
            apiTimeout: $this->config->getTimeout($this->storeId),
            sandbox: $this->config->isSandbox($this->storeId),
            loggingEnabled: $this->config->isLoggingEnabled($this->storeId),
            methodsToLog: $this->config->getMethodsToLog($this->storeId),
        );
    }

    public function getStoreId(): ?int
    {
        return $this->storeId;
    }
}
