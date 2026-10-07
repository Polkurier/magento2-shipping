<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Payout;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Payout\PayoutManager;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sdk\PolkurierClient;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;
use Polkurier\Shipping\Test\Unit\Support\RecordingEventManager;
use Polkurier\Webservice\Api\Service\AccountServiceInterface;
use Polkurier\Webservice\Api\Service\TwoFactorTokenServiceInterface;
use Polkurier\Webservice\Builder\MoneyBox\CreateMoneyBoxPayoutMessageBuilderFactory;
use Polkurier\Webservice\Builder\MoneyBox\GetMoneyBoxPayoutsMessageBuilderFactory;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxFeePaymentTypeEnum;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxPayoutTypeEnum;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Response\Account\AccountInfoMessage;
use Polkurier\Webservice\Response\TwoFactorToken;
use Psr\Log\NullLogger;

#[CoversClass(PayoutManager::class)]
#[AllowMockObjectsWithoutExpectations]
class PayoutManagerTest extends TestCase
{
    private int $codesSent = 0;

    public function testNoCodeIsSentWhileTheMoneyBoxHasNothingToPayOut(): void
    {
        try {
            $this->payouts(0.0)->requestCode();
            self::fail('Expected the code to be refused.');
        } catch (LocalizedException $e) {
            self::assertSame('The money box has nothing to pay out yet. No code was sent.', $e->getMessage());
        }
        self::assertSame(0, $this->codesSent);
    }

    public function testTheCodeIsSentWhenThereIsSomethingToPayOut(): void
    {
        $this->payouts(45.99)->requestCode();

        self::assertSame(1, $this->codesSent);
    }

    public function testTheCodeIsSentWhenTheBalanceCantBeRead(): void
    {
        $this->payouts(null)->requestCode();

        self::assertSame(1, $this->codesSent, 'Polkurier says so itself, should the payout not be possible.');
    }

    /**
     * @return array<string, array{MoneyBoxPayoutTypeEnum, string, ?string, string}>
     */
    public static function incomplete(): array
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

    #[DataProvider('incomplete')]
    public function testAnIncompletePayoutIsNeverSent(
        MoneyBoxPayoutTypeEnum $type,
        string $code,
        ?string $account,
        string $why,
    ): void {
        $client = $this->createMock(PolkurierClient::class);
        $client->expects(self::never())->method('moneyBox');
        $payouts = new PayoutManager(
            $client,
            new ErrorMessageResolver(),
            new ApiEventDispatcher(new RecordingEventManager(), new NullLogger()),
            NewingObjectManager::factory(GetMoneyBoxPayoutsMessageBuilderFactory::class),
            NewingObjectManager::factory(CreateMoneyBoxPayoutMessageBuilderFactory::class),
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($why);
        $payouts->createPayout(
            ['711400-26'],
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
            PayoutManager::chosenAccount(PayoutManager::ANOTHER_ACCOUNT, '61 1090 1014 0000 0712 1981 2874'),
        );
        self::assertSame(
            '61109010140000071219812874',
            PayoutManager::chosenAccount('', '61 1090 1014 0000 0712 1981 2874'),
        );
        self::assertSame(
            'PL27114020040000300201355387',
            PayoutManager::chosenAccount('PL27 1140 2004 0000 3002 0135 5387', 'ignored'),
        );
        self::assertNull(PayoutManager::chosenAccount(PayoutManager::ANOTHER_ACCOUNT, '  '));
    }

    private function payouts(?float $available): PayoutManager
    {
        $info = $this->createStub(AccountInfoMessage::class);
        $info->method('getMoneyBoxPayoutAvailableBalance')->willReturn((float)$available);
        $account = $this->createStub(AccountServiceInterface::class);
        $account->method('getAccountInfo')->willReturnCallback(
            static fn(): AccountInfoMessage => $available === null
                ? throw new ConnectionException('Timed out.')
                : $info,
        );
        $now = new \DateTimeImmutable('2026-10-05 14:30');
        $tokens = $this->createStub(TwoFactorTokenServiceInterface::class);
        $tokens->method('twoFactorTokenRegister')->willReturnCallback(function () use ($now): TwoFactorToken {
            $this->codesSent++;

            return new TwoFactorToken('t1', '', 'Wypłata', 1, $now, $now, $now->modify('+5 minutes'));
        });
        $client = $this->createStub(PolkurierClient::class);
        $client->method('account')->willReturn($account);
        $client->method('twoFactorToken')->willReturn($tokens);

        return new PayoutManager(
            $client,
            new ErrorMessageResolver(),
            new ApiEventDispatcher(new RecordingEventManager(), new NullLogger()),
            NewingObjectManager::factory(GetMoneyBoxPayoutsMessageBuilderFactory::class),
            NewingObjectManager::factory(CreateMoneyBoxPayoutMessageBuilderFactory::class),
        );
    }
}
