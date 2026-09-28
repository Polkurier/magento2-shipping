<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\PackageInfo;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Sdk\StoreConfigProvider;
use Polkurier\Webservice\Enum\Http\ApiMethodEnum;

#[CoversClass(StoreConfigProvider::class)]
#[CoversClass(Config::class)]
class StoreConfigProviderTest extends TestCase
{
    /**
     * Per store: [path => value]. Store 2 belongs to a website with its own account.
     *
     * @var array<int, array<string, string>>
     */
    private array $values = [
        1 => [
            Config::XML_PATH_LOGIN => 'enc:11111',
            Config::XML_PATH_TOKEN => 'enc:token-one',
            Config::XML_PATH_SANDBOX => '1',
            Config::XML_PATH_TIMEOUT => '45',
            Config::XML_PATH_LOGGING => '1',
            Config::XML_PATH_LOG_METHODS => 'create_order,no_such_method,cancel_order',
        ],
        2 => [
            Config::XML_PATH_LOGIN => 'enc:22222',
            Config::XML_PATH_TOKEN => 'enc:token-two',
            Config::XML_PATH_SANDBOX => '0',
            Config::XML_PATH_TIMEOUT => '',
            Config::XML_PATH_LOGGING => '0',
            Config::XML_PATH_LOG_METHODS => '',
            Config::XML_PATH_API_URL => 'http://api.local/',
        ],
    ];

    public function testReadsTheCredentialsOfTheStoreItIsBoundTo(): void
    {
        $one = $this->provider(1)->getConfig();
        $two = $this->provider(2)->getConfig();

        self::assertSame('11111', $one->getAuthorization()->getLogin());
        self::assertSame('token-one', $one->getAuthorization()->getToken());
        self::assertTrue($one->isSandbox());
        self::assertSame(45, $one->getApiTimeout());
        self::assertTrue($one->isLoggingEnabled());
        self::assertSame([ApiMethodEnum::CreateOrder, ApiMethodEnum::CancelOrder], $one->getMethodsToLog());

        self::assertSame('22222', $two->getAuthorization()->getLogin());
        self::assertSame('token-two', $two->getAuthorization()->getToken());
        self::assertFalse($two->isSandbox());
        self::assertSame(30, $two->getApiTimeout(), 'An empty timeout falls back to the SDK default.');
        self::assertFalse($two->isLoggingEnabled());
    }

    public function testIdentifiesTheIntegrationAndTargetsTheConfiguredEndpoint(): void
    {
        $config = $this->provider(1)->getConfig();

        self::assertSame(StoreConfigProvider::PLATFORM, $config->getPlatform());
        self::assertSame('1.2.3', $config->getPlatformVersion());
        self::assertSame(\Polkurier\Webservice\Config::SANDBOX_API_URL, $config->getApiUrl(), 'No endpoint configured: the sandbox flag decides.');
        self::assertSame('http://api.local', $this->provider(2)->getConfig()->getApiUrl());
    }

    public function testResolvesAtCallTimeNotAtConstruction(): void
    {
        $provider = $this->provider(1);
        $this->values[1][Config::XML_PATH_TOKEN] = 'enc:rotated';

        self::assertSame('rotated', $provider->getConfig()->getAuthorization()->getToken());
    }

    private function provider(int $storeId): StoreConfigProvider
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function (string $path, string $scope, ?int $store): ?string {
                self::assertSame(ScopeInterface::SCOPE_STORE, $scope);

                return $this->values[(int)$store][$path] ?? null;
            },
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            fn(string $path, string $scope, ?int $store): bool => (bool)($this->values[(int)$store][$path] ?? false),
        );
        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnCallback(
            static fn(string $value): string => \str_starts_with($value, 'enc:') ? \substr($value, 4) : '',
        );
        $packageInfo = $this->createStub(PackageInfo::class);
        $packageInfo->method('getVersion')->willReturn('1.2.3');

        return new StoreConfigProvider(new Config($scopeConfig, $encryptor), $packageInfo, $storeId);
    }
}
