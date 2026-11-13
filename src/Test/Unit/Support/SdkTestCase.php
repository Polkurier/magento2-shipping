<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Sdk\AccountConfigProvider;
use Polkurier\Shipping\Model\Sdk\AccountConfigProviderFactory;
use Polkurier\Shipping\Model\Sdk\PolkurierClient;
use Polkurier\Shipping\Model\Sdk\SerializerConfigProvider;
use Polkurier\Webservice\PolkurierWebservice;
use Polkurier\Webservice\PolkurierWebserviceFactory;
use Polkurier\Webservice\Serializer\SerializerConfig;
use Polkurier\Webservice\Testing\RecordingClient;
use Polkurier\Webservice\Validator\DefaultValidatorRegistryFactory;

/**
 * Drives the real SDK — validation, serialization, envelope, response mapping — through the
 * SDK's {@see RecordingClient}, so a test can assert both what came back and what was sent.
 */
abstract class SdkTestCase extends TestCase
{
    protected function createClientPool(RecordingClient $http): PolkurierClient
    {
        $webserviceFactory = $this->createStub(PolkurierWebserviceFactory::class);
        $webserviceFactory->method('create')->willReturnCallback(
            static fn(array $arguments): PolkurierWebservice => new PolkurierWebservice(
                ...[...$arguments, 'clientFactory' => $http],
            ),
        );

        $configProvider = $this->createStub(AccountConfigProvider::class);
        $configProvider->method('getConfig')->willReturn(SdkConfig::create());
        $configProviderFactory = $this->createStub(AccountConfigProviderFactory::class);
        $configProviderFactory->method('create')->willReturn($configProvider);

        $serializerConfigs = $this->createStub(SerializerConfigProvider::class);
        $serializerConfigs->method('getConfig')->willReturn(new SerializerConfig());

        return new PolkurierClient(
            $webserviceFactory,
            $configProviderFactory,
            $serializerConfigs,
            DefaultValidatorRegistryFactory::create(),
            $this->createStub(Logger::class),
        );
    }

    /**
     * @param mixed $response the envelope's "response"
     */
    protected static function encodeSuccess(mixed $response): string
    {
        return (string)\json_encode(['status' => 'success', 'response' => $response]);
    }

    protected static function loadFixture(string $name): string
    {
        return (string)\file_get_contents(__DIR__ . '/../_files/' . $name);
    }
}
