<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\PackageInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Config\Source\StatusSchedule;
use Polkurier\Shipping\Model\Sdk\AccountConfigProvider;
use Polkurier\Shipping\Model\Sdk\LogFilesDirectory;
use Polkurier\Webservice\Enum\Http\ApiMethodEnum;

#[CoversClass(AccountConfigProvider::class)]
#[CoversClass(Config::class)]
#[CoversClass(LogFilesDirectory::class)]
class AccountConfigProviderTest extends TestCase
{
    /**
     * The default scope's values, by path.
     *
     * @var array<string, string>
     */
    private array $values = [
        Config::XML_PATH_LOGIN => 'enc:11111',
        Config::XML_PATH_TOKEN => 'enc:token-one',
        Config::XML_PATH_SANDBOX => '1',
        Config::XML_PATH_TIMEOUT => '45',
        Config::XML_PATH_LOGGING => '1',
        Config::XML_PATH_LOG_METHODS => 'create_order,no_such_method,cancel_order',
    ];

    public function testReadsTheAccountFromTheDefaultScopeOnly(): void
    {
        $config = $this->provider()->getConfig();

        self::assertSame('11111', $config->getAuthorization()->getLogin());
        self::assertSame('token-one', $config->getAuthorization()->getToken());
        self::assertTrue($config->isSandbox());
        self::assertSame(45, $config->getApiTimeout());
        self::assertTrue($config->isLoggingEnabled());
        self::assertSame([ApiMethodEnum::CreateOrder, ApiMethodEnum::CancelOrder], $config->getMethodsToLog());
    }

    public function testHidesPersonalDataAndLeavesFilesOutUnlessAllowed(): void
    {
        $config = $this->provider()->getConfig();

        self::assertFalse($config->isPersonalDataLoggingEnabled());
        self::assertFalse($config->isFileLoggingEnabled());
        self::assertFalse($config->isSensitiveDataLoggingEnabled(), 'Secrets are never logged.');
        self::assertSame('/srv/magento/var/log/polkurier-files', $config->getLogFilesDirectory());
    }

    public function testLogsPersonalDataAndSavesFilesWhereAllowed(): void
    {
        $this->values[Config::XML_PATH_LOG_PERSONAL_DATA] = '1';
        $this->values[Config::XML_PATH_LOG_FILES] = '1';
        $this->values[Config::XML_PATH_LOG_FILES_DIRECTORY] = ' var/polkurier/./files ';
        $config = $this->provider()->getConfig();

        self::assertTrue($config->isPersonalDataLoggingEnabled());
        self::assertTrue($config->isFileLoggingEnabled());
        self::assertSame('/srv/magento/var/polkurier/files', $config->getLogFilesDirectory(), 'Relative to Magento\'s root.');

        $this->values[Config::XML_PATH_LOG_FILES_DIRECTORY] = '/var/log/shop/../polkurier';
        self::assertSame('/var/log/polkurier', $this->provider()->getConfig()->getLogFilesDirectory());
    }

    public function testTheStatusIntervalIsReadFromEveryScheduleOffered(): void
    {
        $expected = ['*/10 * * * *' => 10, '*/15 * * * *' => 15, '*/20 * * * *' => 20, '*/30 * * * *' => 30, '0 * * * *' => 60];
        $intervals = [];
        foreach (\array_keys((new StatusSchedule())->toArray()) as $schedule) {
            $this->values[Config::XML_PATH_STATUS_SCHEDULE] = $schedule;
            $intervals[$schedule] = $this->config()->getStatusIntervalMinutes();
        }

        self::assertSame($expected, $intervals);
    }

    public function testAnEmptyTimeoutFallsBackToTheDefault(): void
    {
        $this->values[Config::XML_PATH_TIMEOUT] = '';

        self::assertSame(30, $this->provider()->getConfig()->getApiTimeout());
    }

    public function testIdentifiesTheIntegrationAndTargetsTheConfiguredEndpoint(): void
    {
        $config = $this->provider()->getConfig();

        self::assertSame(AccountConfigProvider::PLATFORM, $config->getPlatform());
        self::assertSame('1.2.3', $config->getPlatformVersion());
        self::assertSame(\Polkurier\Webservice\Config::SANDBOX_API_URL, $config->getApiUrl(), 'No endpoint configured: the sandbox flag decides.');

        $this->values[Config::XML_PATH_API_URL] = 'http://api.local/';
        self::assertSame('http://api.local', $this->provider()->getConfig()->getApiUrl());
    }

    public function testResolvesAtCallTimeNotAtConstruction(): void
    {
        $provider = $this->provider();
        $this->values[Config::XML_PATH_TOKEN] = 'enc:rotated';

        self::assertSame('rotated', $provider->getConfig()->getAuthorization()->getToken());
    }

    private function provider(): AccountConfigProvider
    {
        $packageInfo = $this->createStub(PackageInfo::class);
        $packageInfo->method('getVersion')->willReturn('1.2.3');

        return new AccountConfigProvider($this->config(), $packageInfo, new LogFilesDirectory(self::directoryList()));
    }

    private function config(): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(function (string $path, string $scope): ?string {
            self::assertSame(ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scope, 'A website can\'t have an account of its own.');

            return $this->values[$path] ?? null;
        });
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            fn(string $path, string $scope): bool => $scope === ScopeConfigInterface::SCOPE_TYPE_DEFAULT && (bool)($this->values[$path] ?? false),
        );
        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnCallback(
            static fn(string $value): string => \str_starts_with($value, 'enc:') ? \substr($value, 4) : '',
        );

        return new Config($scopeConfig, $encryptor);
    }

    public static function directoryList(): DirectoryList
    {
        return new DirectoryList('/srv/magento');
    }
}
