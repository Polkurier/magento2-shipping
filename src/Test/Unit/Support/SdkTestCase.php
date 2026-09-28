<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Shipping\Model\Sdk\StoreConfigProvider;
use Polkurier\Shipping\Model\Sdk\StoreConfigProviderFactory;
use Polkurier\Webservice\Config;
use Polkurier\Webservice\Http\Request\AuthorizationMessage;
use Polkurier\Webservice\PolkurierWebservice;
use Polkurier\Webservice\PolkurierWebserviceFactory;
use Polkurier\Webservice\Serializer\SerializerProvider;
use Polkurier\Webservice\Testing\RecordingClient;
use Polkurier\Webservice\Validator\DefaultValidatorRegistryFactory;
use Psr\Log\NullLogger;

/**
 * Drives the real SDK — validation, serialization, envelope, response mapping — through the
 * SDK's RecordingClient, so a test can assert both what came back and what was sent.
 */
abstract class SdkTestCase extends TestCase
{
    protected const string TOKEN = 'test-token-secret';
    protected const string LOGIN = '12345';

    protected function clientPool(RecordingClient $http): ClientPool
    {
        $webserviceFactory = $this->createStub(PolkurierWebserviceFactory::class);
        $webserviceFactory->method('create')->willReturnCallback(
            static fn(array $arguments): PolkurierWebservice => new PolkurierWebservice(...$arguments),
        );

        $configProvider = $this->createStub(StoreConfigProvider::class);
        $configProvider->method('getConfig')->willReturn(new Config(
            authorization: new AuthorizationMessage(token: self::TOKEN, login: self::LOGIN),
            platform: 'magento2',
            platformVersion: '1.0.0',
            apiUrl: 'http://api.test',
        ));
        $configProviderFactory = $this->createStub(StoreConfigProviderFactory::class);
        $configProviderFactory->method('create')->willReturn($configProvider);

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new ClientPool(
            $webserviceFactory,
            $configProviderFactory,
            new SerializerProvider(),
            DefaultValidatorRegistryFactory::create(),
            new NullLogger(),
            $storeManager,
            $http,
        );
    }

    /**
     * @param mixed $response the envelope's "response"
     */
    protected static function success(mixed $response): string
    {
        return (string)\json_encode(['status' => 'success', 'response' => $response]);
    }

    protected static function fixture(string $name): string
    {
        return (string)\file_get_contents(__DIR__ . '/../_files/' . $name);
    }
}
