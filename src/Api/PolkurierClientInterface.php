<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

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

/**
 * The Polkurier API with the shop's account: every service of the SDK, set up as the module uses
 * them (credentials, sandbox, timeouts, logging). Orders placed through getOrders() are not the
 * module's shipments; place those with {@see \Polkurier\Shipping\Api\ShipmentManagementInterface}.
 *
 * @api
 */
interface PolkurierClientInterface
{
    public function getCourier(): CourierServiceInterface;

    public function getOrders(): OrdersServiceInterface;

    public function getAccount(): AccountServiceInterface;

    public function getAddresses(): AddressesServiceInterface;

    public function getBankAccounts(): BankAccountsServiceInterface;

    public function getPackTemplates(): PackTemplatesServiceInterface;

    public function getGeo(): GeoServiceInterface;

    public function getTwoFactorToken(): TwoFactorTokenServiceInterface;

    public function getSystem(): SystemServiceInterface;

    public function getUtils(): UtilsServiceInterface;

    public function getAccountVerification(): AccountVerificationServiceInterface;

    public function getInvoices(): InvoicesServiceInterface;

    public function getPayments(): PaymentsServiceInterface;

    public function getTickets(): TicketsServiceInterface;

    public function getKnowledgeBase(): KnowledgeBaseServiceInterface;

    public function getMoneyBox(): MoneyBoxServiceInterface;
}
