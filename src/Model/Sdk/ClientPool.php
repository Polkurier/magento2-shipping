<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Webservice\Api\Service\AccountServiceInterface;
use Polkurier\Webservice\Api\Service\AddressesServiceInterface;
use Polkurier\Webservice\Api\Service\BankAccountsServiceInterface;
use Polkurier\Webservice\Api\Service\CourierServiceInterface;
use Polkurier\Webservice\Api\Service\GeoServiceInterface;
use Polkurier\Webservice\Api\Service\MoneyBoxServiceInterface;
use Polkurier\Webservice\Api\Service\OrdersServiceInterface;
use Polkurier\Webservice\Api\Service\PackTemplatesServiceInterface;
use Polkurier\Webservice\Api\Service\TwoFactorTokenServiceInterface;
use Polkurier\Webservice\Api\Service\UtilsServiceInterface;
use Polkurier\Webservice\Api\Validation\ValidatorInterface;
use Polkurier\Webservice\Http\ClientFactoryInterface;
use Polkurier\Webservice\PolkurierWebservice;
use Polkurier\Webservice\PolkurierWebserviceFactory;
use Polkurier\Webservice\Serializer\SerializerProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Hands out the SDK's services for a given store.
 *
 * Credentials are per website, and the store whose credentials apply is rarely the current one:
 * in the admin the current store is the admin store, in cron there is none. An SDK service reads
 * its configuration once, when it is constructed, so injecting one directly would pin whichever
 * store happened to be current. This pool keeps one client per store instead, built through DI,
 * and hands out the service interfaces, which are what the rest of the module type-hints.
 */
class ClientPool
{
    /**
     * @var array<int, PolkurierWebservice>
     */
    private array $clients = [];

    public function __construct(
        private readonly PolkurierWebserviceFactory $webserviceFactory,
        private readonly StoreConfigProviderFactory $configProviderFactory,
        private readonly SerializerProviderInterface $serializerProvider,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
        private readonly StoreManagerInterface $storeManager,
        private readonly ?ClientFactoryInterface $clientFactory = null,
    ) {}

    public function courier(?int $storeId = null): CourierServiceInterface
    {
        return $this->client($storeId)->courier();
    }

    public function orders(?int $storeId = null): OrdersServiceInterface
    {
        return $this->client($storeId)->orders();
    }

    public function account(?int $storeId = null): AccountServiceInterface
    {
        return $this->client($storeId)->account();
    }

    public function addresses(?int $storeId = null): AddressesServiceInterface
    {
        return $this->client($storeId)->addresses();
    }

    public function bankAccounts(?int $storeId = null): BankAccountsServiceInterface
    {
        return $this->client($storeId)->bankAccounts();
    }

    public function packTemplates(?int $storeId = null): PackTemplatesServiceInterface
    {
        return $this->client($storeId)->packTemplates();
    }

    public function geo(?int $storeId = null): GeoServiceInterface
    {
        return $this->client($storeId)->geo();
    }

    public function utils(?int $storeId = null): UtilsServiceInterface
    {
        return $this->client($storeId)->utils();
    }

    public function moneyBox(?int $storeId = null): MoneyBoxServiceInterface
    {
        return $this->client($storeId)->moneyBox();
    }

    public function twoFactorToken(?int $storeId = null): TwoFactorTokenServiceInterface
    {
        return $this->client($storeId)->twoFactorToken();
    }

    /**
     * Forgets the clients built so far, e.g. after the credentials were changed in this request.
     */
    public function reset(): void
    {
        $this->clients = [];
    }

    private function client(?int $storeId): PolkurierWebservice
    {
        $storeId ??= (int)$this->storeManager->getStore()->getId();

        if (!isset($this->clients[$storeId])) {
            $configProvider = $this->configProviderFactory->create(['storeId' => $storeId]);
            $this->clients[$storeId] = $this->webserviceFactory->create([
                'config' => $configProvider->getConfig(),
                'logger' => $this->logger,
                'serializerProvider' => $this->serializerProvider,
                'configProvider' => $configProvider,
                'clientFactory' => $this->clientFactory,
                'validator' => $this->validator,
            ]);
        }

        return $this->clients[$storeId];
    }
}
