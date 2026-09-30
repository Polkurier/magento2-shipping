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
 * SDK configuration for the Polkurier account, read from Magento's configuration when it is asked
 * for. The SDK's services read it once, in their constructors; {@see ClientPool} builds them.
 */
class AccountConfigProvider implements ConfigProviderInterface
{
    public const string PLATFORM = 'magento2';
    public const string MODULE_NAME = 'Polkurier_Shipping';

    public function __construct(
        private readonly Config $config,
        private readonly PackageInfo $packageInfo,
    ) {}

    public function getConfig(): ConfigInterface
    {
        return new SdkConfig(
            authorization: new AuthorizationMessage(
                token: $this->config->getToken(),
                login: $this->config->getLogin(),
            ),
            platform: self::PLATFORM,
            platformVersion: (string)($this->packageInfo->getVersion(self::MODULE_NAME) ?: '1.0.0'),
            apiUrl: $this->config->getApiUrl(),
            apiTimeout: $this->config->getTimeout(),
            sandbox: $this->config->isSandbox(),
            loggingEnabled: $this->config->isLoggingEnabled(),
            methodsToLog: $this->config->getMethodsToLog(),
        );
    }
}
