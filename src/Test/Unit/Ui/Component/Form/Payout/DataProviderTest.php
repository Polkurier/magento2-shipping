<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Form\Payout;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Payout\MoneyBoxOrderFinder;
use Polkurier\Shipping\Model\Payout\PayoutManager;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Ui\Component\Form\Payout\DataProvider;
use Polkurier\Webservice\Dto\Response\MoneyBox\MoneyBoxConfigMessage;

#[CoversClass(DataProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class DataProviderTest extends TestCase
{
    public function testSettingsPolkurierLeavesOutStayUnchosen(): void
    {
        $data = $this->createProvider(new MoneyBoxConfigMessage(
            payoutType: null,
            feePayment: null,
            bankAccountNumber: '61 1090 1014 0000 0712 1981 2874',
        ))->getData()[''];

        self::assertNull($data['payout_type']);
        self::assertNull($data['fee_payment_type']);
        self::assertSame('61109010140000071219812874', $data['bank_account_number']);
    }

    private function createProvider(MoneyBoxConfigMessage $config): DataProvider
    {
        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturn($this->createStub(Collection::class));
        $payouts = $this->createStub(PayoutManager::class);
        $payouts->method('getConfig')->willReturn($config);
        $settings = $this->createStub(ConfigProvider::class);
        $settings->method('hasCredentials')->willReturn(true);

        return new DataProvider(
            'polkurier_payout_form_data_source',
            'id',
            'id',
            $collections,
            $payouts,
            $settings,
            $this->createStub(UrlInterface::class),
            $this->createStub(DataPersistorInterface::class),
            $this->createStub(BankAccountRepositoryInterface::class),
            $this->createStub(MoneyBoxOrderFinder::class),
        );
    }
}
