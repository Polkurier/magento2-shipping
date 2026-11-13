<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Sdk\AccountConfigProvider;
use Polkurier\Shipping\Model\Sdk\AccountConfigProviderFactory;
use Polkurier\Shipping\Model\Sdk\PolkurierClient;
use Polkurier\Shipping\Model\Sdk\SerializerConfigProvider;
use Polkurier\Shipping\Test\Unit\Support\SdkConfig;
use Polkurier\Webservice\PolkurierWebservice;
use Polkurier\Webservice\PolkurierWebserviceFactory;
use Polkurier\Webservice\Serializer\SerializerConfig;
use Polkurier\Webservice\Validator\DefaultValidatorRegistryFactory;

#[CoversClass(PolkurierClient::class)]
#[AllowMockObjectsWithoutExpectations]
class PolkurierClientTest extends TestCase
{
    private int $built = 0;

    public function testItHandsOutEveryServiceOfTheSdk(): void
    {
        $services = static fn(string $class): array => \array_values(\array_map(
            static fn(\ReflectionMethod $method): string => $method->getName(),
            \array_filter(
                (new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC),
                static fn(\ReflectionMethod $method): bool => \str_starts_with(
                    (string)$method->getReturnType(),
                    'Polkurier\\Webservice\\Api\\',
                ),
            ),
        ));

        self::assertSame(
            \array_map(
                static fn(string $name): string => 'get' . \ucfirst($name),
                $services(PolkurierWebservice::class),
            ),
            $services(PolkurierClientInterface::class),
        );
    }

    public function testOneClientServesTheWholeRequest(): void
    {
        $client = $this->createClient();

        $client->getOrders();
        $client->getCourier();
        $client->getOrders();

        self::assertSame(1, $this->built);
    }

    public function testNewCredentialsGetANewClient(): void
    {
        $client = $this->createClient();

        $client->getOrders();
        $client->reset();
        $client->getOrders();

        self::assertSame(2, $this->built, 'The configuration is read again after a reset.');
    }

    private function createClient(): PolkurierClient
    {
        $configProviders = $this->createStub(AccountConfigProviderFactory::class);
        $configProviders->method('create')->willReturnCallback(function (): AccountConfigProvider {
            $this->built++;
            $provider = $this->createStub(AccountConfigProvider::class);
            $provider->method('getConfig')->willReturn(SdkConfig::create());

            return $provider;
        });
        $webservices = $this->createStub(PolkurierWebserviceFactory::class);
        $webservices->method('create')->willReturnCallback(
            static fn(array $arguments): PolkurierWebservice => new PolkurierWebservice(...$arguments),
        );

        $serializerConfigs = $this->createStub(SerializerConfigProvider::class);
        $serializerConfigs->method('getConfig')->willReturn(new SerializerConfig());

        return new PolkurierClient(
            $webservices,
            $configProviders,
            $serializerConfigs,
            DefaultValidatorRegistryFactory::create(),
            $this->createStub(Logger::class),
        );
    }
}
