<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

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

/**
 * The Polkurier API with the shop's account: the SDK's services, set up as the module uses them
 * (credentials, sandbox, timeouts, logging). Orders placed through orders() are not the module's
 * shipments; place those with {@see \Polkurier\Shipping\Api\ShipmentManagementInterface}.
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

    public function utils(): UtilsServiceInterface;

    public function moneyBox(): MoneyBoxServiceInterface;

    public function system(): SystemServiceInterface;

    public function twoFactorToken(): TwoFactorTokenServiceInterface;
}
