<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Payout;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Payout\PayoutManager;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sdk\PolkurierClient;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;
use Polkurier\Shipping\Test\Unit\Support\RecordingEventManager;
use Polkurier\Webservice\Api\Service\AccountServiceInterface;
use Polkurier\Webservice\Api\Service\TwoFactorTokenServiceInterface;
use Polkurier\Webservice\Builder\Request\MoneyBox\CreateMoneyBoxPayoutMessageBuilderFactory;
use Polkurier\Webservice\Builder\Request\MoneyBox\GetMoneyBoxPayoutsMessageBuilderFactory;
use Polkurier\Webservice\Dto\Response\Account\AccountInfoMessage;
use Polkurier\Webservice\Dto\Response\TwoFactorToken;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxFeePaymentTypeEnum;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxPayoutTypeEnum;
use Polkurier\Webservice\Exception\ConnectionException;

#[CoversClass(PayoutManager::class)]
#[AllowMockObjectsWithoutExpectations]
class PayoutManagerTest extends TestCase
{
    private int $codesSent = 0;

    public function testNoCodeIsSentWhileTheMoneyBoxHasNothingToPayOut(): void
    {
        try {
            $this->createManager(0.0)->requestCode();
            self::fail('Expected the code to be refused.');
        } catch (LocalizedException $e) {
            self::assertSame('The money box has nothing to pay out yet. No code was sent.', $e->getMessage());
        }
        self::assertSame(0, $this->codesSent);
    }

    public function testTheCodeIsSentWhenThereIsSomethingToPayOut(): void
    {
        $this->createManager(45.99)->requestCode();

        self::assertSame(1, $this->codesSent);
    }

    public function testTheCodeIsSentWhenTheBalanceCantBeRead(): void
    {
        $this->createManager(null)->requestCode();

        self::assertSame(1, $this->codesSent, 'Polkurier says so itself, should the payout not be possible.');
    }

    /**
     * @return array<string, array{MoneyBoxPayoutTypeEnum, string, ?string, string}>
     */
    public static function provideIncomplete(): array
    {
        return [
            'a transfer without an account' => [
                MoneyBoxPayoutTypeEnum::Account,
                '123456',
                null,
                'Enter the bank account the payout is transferred to.',
            ],
            'without the code' => [
                MoneyBoxPayoutTypeEnum::Postal,
                '',
                null,
                'Enter the confirmation code Polkurier sent you.',
            ],
        ];
    }

    #[DataProvider('provideIncomplete')]
    public function testAnIncompletePayoutIsNeverSent(
        MoneyBoxPayoutTypeEnum $type,
        string $code,
        ?string $account,
        string $why,
    ): void {
        $client = $this->createMock(PolkurierClient::class);
        $client->expects(self::never())->method('getMoneyBox');
        $payouts = new PayoutManager(
            $client,
            new ErrorMessageResolver(),
            new ApiEventDispatcher(new RecordingEventManager(), $this->createStub(Logger::class)),
            NewingObjectManager::createFactory(GetMoneyBoxPayoutsMessageBuilderFactory::class),
            NewingObjectManager::createFactory(CreateMoneyBoxPayoutMessageBuilderFactory::class),
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($why);
        $payouts->createPayout(
            ['24680-26'],
            $type,
            MoneyBoxFeePaymentTypeEnum::MoneyBox,
            'token-1',
            $code,
            null,
            $account,
        );
    }

    public function testATransferGoesToTheAccountChosenOrTheOneTypedIn(): void
    {
        self::assertSame(
            '61109010140000071219812874',
            PayoutManager::resolveChosenAccount(PayoutManager::ANOTHER_ACCOUNT, '61 1090 1014 0000 0712 1981 2874'),
        );
        self::assertSame(
            '61109010140000071219812874',
            PayoutManager::resolveChosenAccount('', '61 1090 1014 0000 0712 1981 2874'),
        );
        self::assertSame(
            'PL27114020040000300201355387',
            PayoutManager::resolveChosenAccount('PL27 1140 2004 0000 3002 0135 5387', 'ignored'),
        );
        self::assertNull(PayoutManager::resolveChosenAccount(PayoutManager::ANOTHER_ACCOUNT, '  '));
    }

    private function createManager(?float $available): PayoutManager
    {
        $info = new AccountInfoMessage(moneyBoxPayoutAvailableBalance: (float)$available);
        $account = $this->createStub(AccountServiceInterface::class);
        $account->method('getAccountInfo')->willReturnCallback(
            static fn(): AccountInfoMessage => $available === null
                ? throw new ConnectionException('Timed out.')
                : $info,
        );
        $now = new \DateTimeImmutable('2026-10-05 14:30');
        $tokens = $this->createStub(TwoFactorTokenServiceInterface::class);
        $tokens->method('register')->willReturnCallback(function () use ($now): TwoFactorToken {
            $this->codesSent++;

            return new TwoFactorToken('t1', '', 'Wypłata', 1, $now, $now, $now->modify('+5 minutes'));
        });
        $client = $this->createStub(PolkurierClient::class);
        $client->method('getAccount')->willReturn($account);
        $client->method('getTwoFactorToken')->willReturn($tokens);

        return new PayoutManager(
            $client,
            new ErrorMessageResolver(),
            new ApiEventDispatcher(new RecordingEventManager(), $this->createStub(Logger::class)),
            NewingObjectManager::createFactory(GetMoneyBoxPayoutsMessageBuilderFactory::class),
            NewingObjectManager::createFactory(CreateMoneyBoxPayoutMessageBuilderFactory::class),
        );
    }
}
