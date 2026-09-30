<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Polkurier\Webservice\Api\Service\AccountServiceInterface;
use Polkurier\Webservice\Api\Service\AddressesServiceInterface;
use Polkurier\Webservice\Api\Service\BankAccountsServiceInterface;
use Polkurier\Webservice\Api\Service\CourierServiceInterface;
use Polkurier\Webservice\Api\Service\GeoServiceInterface;
use Polkurier\Webservice\Api\Service\MoneyBoxServiceInterface;
use Polkurier\Webservice\Api\Service\OrdersServiceInterface;
use Polkurier\Webservice\Api\Service\PackTemplatesServiceInterface;
use Polkurier\Webservice\Api\Service\SystemServiceInterface;
use Polkurier\Webservice\Api\Service\TwoFactorTokenServiceInterface;
use Polkurier\Webservice\Api\Service\UtilsServiceInterface;
use Polkurier\Webservice\Api\Validation\ValidatorInterface;
use Polkurier\Webservice\Http\ClientFactoryInterface;
use Polkurier\Webservice\PolkurierWebservice;
use Polkurier\Webservice\PolkurierWebserviceFactory;
use Polkurier\Webservice\Serializer\SerializerProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Hands out the SDK's services for the Polkurier account.
 *
 * An SDK service reads its configuration once, when it is constructed, so the client is built
 * when first needed and kept for the request, and rebuilt after the credentials change
 * (reset()). The pool hands out the service interfaces, which are what the rest of the module
 * type-hints.
 */
class ClientPool
{
    private ?PolkurierWebservice $client = null;

    public function __construct(
        private readonly PolkurierWebserviceFactory $webserviceFactory,
        private readonly AccountConfigProviderFactory $configProviderFactory,
        private readonly SerializerProviderInterface $serializerProvider,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
        private readonly ?ClientFactoryInterface $clientFactory = null,
    ) {}

    public function courier(): CourierServiceInterface
    {
        return $this->client()->courier();
    }

    public function orders(): OrdersServiceInterface
    {
        return $this->client()->orders();
    }

    public function account(): AccountServiceInterface
    {
        return $this->client()->account();
    }

    public function addresses(): AddressesServiceInterface
    {
        return $this->client()->addresses();
    }

    public function bankAccounts(): BankAccountsServiceInterface
    {
        return $this->client()->bankAccounts();
    }

    public function packTemplates(): PackTemplatesServiceInterface
    {
        return $this->client()->packTemplates();
    }

    public function geo(): GeoServiceInterface
    {
        return $this->client()->geo();
    }

    public function utils(): UtilsServiceInterface
    {
        return $this->client()->utils();
    }

    public function moneyBox(): MoneyBoxServiceInterface
    {
        return $this->client()->moneyBox();
    }

    public function system(): SystemServiceInterface
    {
        return $this->client()->system();
    }

    public function twoFactorToken(): TwoFactorTokenServiceInterface
    {
        return $this->client()->twoFactorToken();
    }

    /**
     * Forgets the clients built so far, e.g. after the credentials were changed in this request.
     */
    public function reset(): void
    {
        $this->client = null;
    }

    private function client(): PolkurierWebservice
    {
        if ($this->client === null) {
            $configProvider = $this->configProviderFactory->create();
            $this->client = $this->webserviceFactory->create([
                'config' => $configProvider->getConfig(),
                'logger' => $this->logger,
                'serializerProvider' => $this->serializerProvider,
                'configProvider' => $configProvider,
                'clientFactory' => $this->clientFactory,
                'validator' => $this->validator,
            ]);
        }

        return $this->client;
    }
}
