<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
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
 * them (credentials, sandbox, timeouts, logging). Orders placed through orders() are not the
 * module's shipments; place those with {@see \Polkurier\Shipping\Api\ShipmentManagementInterface}.
 *
 * @api
 */
interface PolkurierClientInterface
{
    public function courier(): CourierServiceInterface;

    public function orders(): OrdersServiceInterface;

    public function account(): AccountServiceInterface;

    public function addresses(): AddressesServiceInterface;

    public function bankAccounts(): BankAccountsServiceInterface;

    public function packTemplates(): PackTemplatesServiceInterface;

    public function geo(): GeoServiceInterface;

    public function twoFactorToken(): TwoFactorTokenServiceInterface;

    public function system(): SystemServiceInterface;

    public function utils(): UtilsServiceInterface;

    public function accountVerification(): AccountVerificationServiceInterface;

    public function invoices(): InvoicesServiceInterface;

    public function payments(): PaymentsServiceInterface;

    public function tickets(): TicketsServiceInterface;

    public function knowledgeBase(): KnowledgeBaseServiceInterface;

    public function moneyBox(): MoneyBoxServiceInterface;
}
