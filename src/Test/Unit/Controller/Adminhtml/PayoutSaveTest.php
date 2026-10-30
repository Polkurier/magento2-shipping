<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Controller\Adminhtml\Payout\Save;
use Polkurier\Shipping\Enum\Adminhtml\FormMemoryEnum;
use Polkurier\Shipping\Model\Payout\PayoutManager;
use Polkurier\Shipping\Test\Unit\Support\AdminActionTestCase;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxFeePaymentTypeEnum;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxPayoutTypeEnum;
use Polkurier\Webservice\Response\MoneyBox\MoneyBoxPayoutMessage;

#[CoversClass(Save::class)]
#[AllowMockObjectsWithoutExpectations]
class PayoutSaveTest extends AdminActionTestCase
{
    /**
     * @var array<int, mixed>|null what the payout was requested with
     */
    private ?array $requested = null;

    /**
     * @var array<string, mixed>|null what the form gets back
     */
    private ?array $kept = null;

    public function testAPayoutToABankAccountIsRequestedWithItsCode(): void
    {
        $this->post = [
            'orders' => "24680-26\n24680-27",
            'payout_type' => 'ACCOUNT',
            'fee_payment_type' => 'PREPAID',
            'bank_account_choice' => 'new',
            'bank_account_number' => '61 1090 1014 0000 0712 1981 2874',
            'two_factor_token' => 'token-1',
            'two_factor_code' => '123456',
        ];

        $this->save()->execute();

        self::assertSame([
            ['24680-26', '24680-27'],
            MoneyBoxPayoutTypeEnum::Account,
            MoneyBoxFeePaymentTypeEnum::Prepaid,
            'token-1',
            '123456',
            null,
            '61109010140000071219812874',
        ], $this->requested);
        self::assertSame(['Payout W-17 requested: 120.50 PLN.'], $this->messages['success']);
        self::assertSame(['*/*/index', []], $this->redirect);
    }

    public function testAPostalOrderNeedsNoAccount(): void
    {
        $this->post = ['payout_type' => 'POSTAL', 'two_factor_token' => 'token-1', 'two_factor_code' => '123456'];

        $this->save()->execute();

        self::assertSame(MoneyBoxPayoutTypeEnum::Postal, $this->requested[1] ?? null);
        self::assertNull($this->requested[6] ?? null);
        self::assertSame(
            MoneyBoxFeePaymentTypeEnum::MoneyBox,
            $this->requested[2] ?? null,
            'The fee from the money box unless said otherwise.',
        );
    }

    public function testATransferWithoutAnAccountGoesBackToTheFormWithWhatWasChosen(): void
    {
        $this->post = [
            'orders' => '24680-26',
            'payout_type' => 'ACCOUNT',
            'bank_account_choice' => '',
            'two_factor_token' => 'token-1',
            'two_factor_code' => '123456',
        ];

        $this->save(new LocalizedException(__('Enter the bank account the payout is transferred to.')))->execute();

        self::assertNull($this->requested[6] ?? null, 'No account chosen, none typed in.');
        self::assertSame(['Enter the bank account the payout is transferred to.'], $this->messages['error']);
        self::assertSame(['*/*/request', []], $this->redirect);
        self::assertSame(
            ['orders' => '24680-26', 'payout_type' => 'ACCOUNT', 'bank_account_choice' => ''],
            $this->kept,
        );
    }

    public function testTheCodeIsntKeptForTheFormWhenThePayoutIsRefused(): void
    {
        $this->post = [
            'payout_type' => 'POSTAL',
            'two_factor_token' => 'token-1',
            'two_factor_code' => '',
            'security_pin' => '1234',
        ];

        $this->save(new LocalizedException(__('Enter the confirmation code Polkurier sent you.')))->execute();

        self::assertSame(['Enter the confirmation code Polkurier sent you.'], $this->messages['error']);
        self::assertSame(['payout_type' => 'POSTAL'], $this->kept, 'The token, the code and the PIN are asked again.');
    }

    public function testPolkurierRefusingGoesBackToTheForm(): void
    {
        $this->post = ['payout_type' => 'POSTAL', 'two_factor_token' => 'token-1', 'two_factor_code' => '000000'];

        $this->save(new LocalizedException(__('The code is wrong.')))->execute();

        self::assertSame(['The code is wrong.'], $this->messages['error']);
        self::assertSame(['*/*/request', []], $this->redirect);
    }

    private function save(?LocalizedException $refusal = null): Save
    {
        $payouts = $this->createStub(PayoutManager::class);
        $payouts->method('createPayout')
            ->willReturnCallback(function (mixed ...$args) use ($refusal): MoneyBoxPayoutMessage {
                $this->requested = $args;
                if ($refusal !== null) {
                    throw $refusal;
                }
                $payout = $this->createStub(MoneyBoxPayoutMessage::class);
                $payout->method('getNumber')->willReturn('W-17');
                $payout->method('getAmount')->willReturn(120.5);

                return $payout;
            });
        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('set')->willReturnCallback(function (string $key, mixed $value): void {
            self::assertSame(FormMemoryEnum::Payout->value, $key);
            $this->kept = $value;
        });

        return new Save($this->context(), $payouts, $persistor);
    }
}
