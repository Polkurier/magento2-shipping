<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Sdk\AccountConfigProvider;
use Polkurier\Shipping\Model\Sdk\AccountConfigProviderFactory;
use Polkurier\Shipping\Model\Sdk\PolkurierClient;
use Polkurier\Webservice\Config;
use Polkurier\Webservice\Http\Request\AuthorizationMessage;
use Polkurier\Webservice\PolkurierWebservice;
use Polkurier\Webservice\PolkurierWebserviceFactory;
use Polkurier\Webservice\Serializer\SerializerProvider;
use Polkurier\Webservice\Validator\DefaultValidatorRegistryFactory;
use Psr\Log\NullLogger;

#[CoversClass(PolkurierClient::class)]
#[AllowMockObjectsWithoutExpectations]
class PolkurierClientTest extends TestCase
{
    private int $built = 0;

    public function testOneClientServesTheWholeRequest(): void
    {
        $client = $this->client();

        $client->orders();
        $client->courier();
        $client->orders();

        self::assertSame(1, $this->built);
    }

    public function testNewCredentialsGetANewClient(): void
    {
        $client = $this->client();

        $client->orders();
        $client->reset();
        $client->orders();

        self::assertSame(2, $this->built, 'The configuration is read again after a reset.');
    }

    private function client(): PolkurierClient
    {
        $configProviders = $this->createStub(AccountConfigProviderFactory::class);
        $configProviders->method('create')->willReturnCallback(function (): AccountConfigProvider {
            $this->built++;
            $provider = $this->createStub(AccountConfigProvider::class);
            $provider->method('getConfig')->willReturn(new Config(
                authorization: new AuthorizationMessage(token: 'token', login: '12345'),
                platform: 'magento2',
                platformVersion: '1.0.0',
                apiUrl: 'http://api.test',
            ));

            return $provider;
        });
        $webservices = $this->createStub(PolkurierWebserviceFactory::class);
        $webservices->method('create')->willReturnCallback(
            static fn(array $arguments): PolkurierWebservice => new PolkurierWebservice(...$arguments),
        );

        return new PolkurierClient(
            $webservices,
            $configProviders,
            new SerializerProvider(),
            DefaultValidatorRegistryFactory::create(),
            new NullLogger(),
        );
    }
}
