<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Webservice\Api\Service\AccountServiceInterface;
use Polkurier\Webservice\Api\Service\AccountVerificationServiceInterface;
use Polkurier\Webservice\Api\Service\AddressesServiceInterface;
use Polkurier\Webservice\Api\Service\BankAccountsServiceInterface;
use Polkurier\Webservice\Api\Service\CourierServiceInterface;
use Polkurier\Webservice\Api\Service\GeoServiceInterface;
use Polkurier\Webservice\Api\Service\InvoicesServiceInterface;
use Polkurier\Webservice\Api\Service\KnowledgeBaseServiceInterface;
use Polkurier\Webservice\Api\Service\MoneyBoxServiceInterface;
use Polkurier\Webservice\Api\Service\OrdersServiceInterface;
use Polkurier\Webservice\Api\Service\PackTemplatesServiceInterface;
use Polkurier\Webservice\Api\Service\PaymentsServiceInterface;
use Polkurier\Webservice\Api\Service\SystemServiceInterface;
use Polkurier\Webservice\Api\Service\TicketsServiceInterface;
use Polkurier\Webservice\Api\Service\TwoFactorTokenServiceInterface;
use Polkurier\Webservice\Api\Service\UtilsServiceInterface;
use Polkurier\Webservice\Api\Validation\ValidatorInterface;
use Polkurier\Webservice\PolkurierWebservice;
use Polkurier\Webservice\PolkurierWebserviceFactory;
use Polkurier\Webservice\Serializer\SerializerProviderInterface;

/**
 * Hands out the SDK's services for the Polkurier account.
 *
 * An SDK service reads its configuration once, when it is constructed, so the client is built
 * when first needed and kept for the request, and rebuilt after the credentials change
 * (reset()). The client hands out the service interfaces, which are what the rest of the module
 * type-hints.
 */
class PolkurierClient implements PolkurierClientInterface, ResetAfterRequestInterface
{
    private ?PolkurierWebservice $client = null;

    public function __construct(
        private readonly PolkurierWebserviceFactory $webserviceFactory,
        private readonly AccountConfigProviderFactory $configProviderFactory,
        private readonly SerializerProviderInterface $serializerProvider,
        private readonly ValidatorInterface $validator,
        private readonly Logger $logger,
    ) {}

    public function getCourier(): CourierServiceInterface
    {
        return $this->getClient()->courier();
    }

    public function getOrders(): OrdersServiceInterface
    {
        return $this->getClient()->orders();
    }

    public function getAccount(): AccountServiceInterface
    {
        return $this->getClient()->account();
    }

    public function getAddresses(): AddressesServiceInterface
    {
        return $this->getClient()->addresses();
    }

    public function getBankAccounts(): BankAccountsServiceInterface
    {
        return $this->getClient()->bankAccounts();
    }

    public function getPackTemplates(): PackTemplatesServiceInterface
    {
        return $this->getClient()->packTemplates();
    }

    public function getGeo(): GeoServiceInterface
    {
        return $this->getClient()->geo();
    }

    public function getTwoFactorToken(): TwoFactorTokenServiceInterface
    {
        return $this->getClient()->twoFactorToken();
    }

    public function getSystem(): SystemServiceInterface
    {
        return $this->getClient()->system();
    }

    public function getUtils(): UtilsServiceInterface
    {
        return $this->getClient()->utils();
    }

    public function getAccountVerification(): AccountVerificationServiceInterface
    {
        return $this->getClient()->accountVerification();
    }

    public function getInvoices(): InvoicesServiceInterface
    {
        return $this->getClient()->invoices();
    }

    public function getPayments(): PaymentsServiceInterface
    {
        return $this->getClient()->payments();
    }

    public function getTickets(): TicketsServiceInterface
    {
        return $this->getClient()->tickets();
    }

    public function getKnowledgeBase(): KnowledgeBaseServiceInterface
    {
        return $this->getClient()->knowledgeBase();
    }

    public function getMoneyBox(): MoneyBoxServiceInterface
    {
        return $this->getClient()->moneyBox();
    }

    /**
     * Forgets the clients built so far, e.g. after the credentials were changed in this request.
     */
    public function reset(): void
    {
        $this->client = null;
    }

    public function _resetState(): void
    {
        $this->reset();
    }

    private function getClient(): PolkurierWebservice
    {
        if ($this->client === null) {
            $configProvider = $this->configProviderFactory->create();
            $this->client = $this->webserviceFactory->create([
                'config' => $configProvider->getConfig(),
                'logger' => $this->logger,
                'serializerProvider' => $this->serializerProvider,
                'configProvider' => $configProvider,
                'validator' => $this->validator,
            ]);
        }

        return $this->client;
    }
}
