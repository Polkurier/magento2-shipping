<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\MoneyBox;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\ApiEventDispatcher;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\MoneyBox\Payouts;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Shipping\Test\Unit\Support\RecordingEventManager;
use Polkurier\Webservice\Api\Service\AccountServiceInterface;
use Polkurier\Webservice\Api\Service\TwoFactorTokenServiceInterface;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Response\Account\AccountInfoMessage;
use Polkurier\Webservice\Response\TwoFactorToken;
use Psr\Log\NullLogger;

#[CoversClass(Payouts::class)]
class PayoutsTest extends TestCase
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

    private function payouts(?float $available): Payouts
    {
        $info = $this->createStub(AccountInfoMessage::class);
        $info->method('getMoneyBoxPayoutAvailableBalance')->willReturn((float)$available);
        $account = $this->createStub(AccountServiceInterface::class);
        $account->method('getAccountInfo')->willReturnCallback(
            static fn(): AccountInfoMessage => $available === null ? throw new ConnectionException('Timed out.') : $info,
        );
        $now = new \DateTimeImmutable('2026-10-05 14:30');
        $tokens = $this->createStub(TwoFactorTokenServiceInterface::class);
        $tokens->method('twoFactorTokenRegister')->willReturnCallback(function () use ($now): TwoFactorToken {
            $this->codesSent++;

            return new TwoFactorToken('t1', '', 'Wypłata', 1, $now, $now, $now->modify('+5 minutes'));
        });
        $pool = $this->createStub(ClientPool::class);
        $pool->method('account')->willReturn($account);
        $pool->method('twoFactorToken')->willReturn($tokens);

        return new Payouts($pool, new ErrorMessages(), new ApiEventDispatcher(new RecordingEventManager(), new NullLogger()));
    }
}
