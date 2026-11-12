<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\Module\PackageInfo;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Webservice\Api\ConfigInterface;
use Polkurier\Webservice\Api\ConfigProviderInterface;
use Polkurier\Webservice\ConfigFactory as SdkConfigFactory;
use Polkurier\Webservice\Http\Request\AuthorizationMessageFactory;

/**
 * SDK configuration for the Polkurier account, read from Magento's configuration when it is asked
 * for. The SDK's services read it once, in their constructors;
 * {@see \Polkurier\Shipping\Model\Sdk\PolkurierClient} builds them.
 */
readonly class AccountConfigProvider implements ConfigProviderInterface
{
    public const string PLATFORM = 'magento2';
    public const string MODULE_NAME = 'Polkurier_Shipping';

    public function __construct(
        private ConfigProvider $configProvider,
        private PackageInfo $packageInfo,
        private LogFilesDirectoryResolver $logFilesDirectoryResolver,
        private DateTime $dateTime,
        private SdkConfigFactory $sdkConfigFactory,
        private AuthorizationMessageFactory $authorizationMessageFactory,
        // At most this many seconds, whatever the setting says: the storefront's customers don't
        // wait as long as the admin does (etc/frontend/di.xml).
        private ?int $maxTimeout = null,
    ) {}

    public function getConfig(): ConfigInterface
    {
        return $this->sdkConfigFactory->create([
            'authorization' => $this->authorizationMessageFactory->create([
                'token' => $this->configProvider->getToken(),
                'login' => $this->configProvider->getLogin(),
            ]),
            'platform' => self::PLATFORM,
            'platformVersion' => (string)($this->packageInfo->getVersion(self::MODULE_NAME) ?: '1.0.0'),
            'apiUrl' => $this->configProvider->getApiUrl(),
            'apiTimeout' => $this->maxTimeout !== null
                ? \min($this->configProvider->getTimeout(), $this->maxTimeout)
                : $this->configProvider->getTimeout(),
            'sandbox' => $this->configProvider->isSandbox(),
            'loggingEnabled' => $this->configProvider->isLoggingEnabled(),
            'methodsToLog' => $this->configProvider->getMethodsToLog(),
            'logPersonalData' => $this->configProvider->isPersonalDataLoggingEnabled(),
            'logFiles' => $this->configProvider->isFileLoggingEnabled(),
            // This month's, so a month's files can be dealt with together.
            'logFilesDirectory' => $this->logFilesDirectoryResolver->resolveForMonth(
                $this->logFilesDirectoryResolver->resolve($this->configProvider->getLogFilesDirectory()),
                $this->dateTime->gmtTimestamp(),
            ),
        ]);
    }
}
