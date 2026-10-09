<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Payout;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Enum\Sdk\ApiEventEnum;
use Polkurier\Shipping\Model\BankAccount\BankAccountMapper;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Webservice\Builder\MoneyBox\CreateMoneyBoxPayoutMessageBuilderFactory;
use Polkurier\Webservice\Builder\MoneyBox\GetMoneyBoxPayoutsMessageBuilderFactory;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxFeePaymentTypeEnum;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxPayoutTypeEnum;
use Polkurier\Webservice\Enum\TwoFactorToken\TwoFactorNamespaceEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Request\MoneyBox\CalculateMoneyBoxPayoutFeeMessage;
use Polkurier\Webservice\Request\MoneyBox\CancelMoneyBoxPayoutMessage;
use Polkurier\Webservice\Request\TwoFactorToken\RegisterMessage;
use Polkurier\Webservice\Response\MoneyBox\MoneyBoxConfigMessage;
use Polkurier\Webservice\Response\MoneyBox\MoneyBoxFeeMessage;
use Polkurier\Webservice\Response\MoneyBox\MoneyBoxPayoutListMessage;
use Polkurier\Webservice\Response\MoneyBox\MoneyBoxPayoutMessage;
use Polkurier\Webservice\Response\TwoFactorToken;

/**
 * Cash collected on delivery and held in the Polkurier money box, and paying it out.
 *
 * A payout moves real money. Polkurier requires a two-factor code for it, and the security PIN
 * when the account has locked its money box settings; neither is stored.
 */
readonly class PayoutManager
{
    /**
     * The bank account choice that uses the number typed in.
     */
    public const string ANOTHER_ACCOUNT = 'new';

    public function __construct(
        private PolkurierClientInterface $polkurierClient,
        private ErrorMessageResolver $errorMessageResolver,
        private ApiEventDispatcher $apiEventDispatcher,
        private GetMoneyBoxPayoutsMessageBuilderFactory $getMoneyBoxPayoutsMessageBuilderFactory,
        private CreateMoneyBoxPayoutMessageBuilderFactory $createMoneyBoxPayoutMessageBuilderFactory,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function getConfig(): MoneyBoxConfigMessage
    {
        return $this->call(fn() => $this->polkurierClient->moneyBox()->getConfig());
    }

    /**
     * @throws LocalizedException
     */
    public function getPayouts(int $page = 1, int $pageSize = 20): MoneyBoxPayoutListMessage
    {
        return $this->call(fn() => $this->polkurierClient->moneyBox()->getPayouts(
            $this->getMoneyBoxPayoutsMessageBuilderFactory->create()->pageSize($pageSize)->page($page)->build(),
        ));
    }

    /**
     * What can be paid out now, in złoty, or null when Polkurier can't be asked.
     */
    public function getAvailableBalance(): ?float
    {
        try {
            return $this->call(
                fn(): float => $this->polkurierClient->account()->getAccountInfo()->getMoneyBoxPayoutAvailableBalance(),
            );
        } catch (LocalizedException) {
            return null;
        }
    }

    /**
     * The account a transfer goes to: one of the bank accounts, or the number typed in; none when
     * neither is given.
     *
     * @param string $choice a bank account's number, or ANOTHER_ACCOUNT
     */
    public static function chosenAccount(string $choice, string $typed): ?string
    {
        $number = BankAccountMapper::normalizeNumber(
            $choice === self::ANOTHER_ACCOUNT || $choice === '' ? $typed : $choice,
        );

        return $number !== '' ? $number : null;
    }

    /**
     * What a payout of the given orders (or of everything available, when none) would cost.
     *
     * @param string[] $orderNumbers
     *
     * @throws LocalizedException
     */
    public function quote(array $orderNumbers, ?MoneyBoxPayoutTypeEnum $payoutType = null): MoneyBoxFeeMessage
    {
        return $this->call(fn() => $this->polkurierClient->moneyBox()->calculatePayoutFee(
            new CalculateMoneyBoxPayoutFeeMessage(orders: \array_values($orderNumbers), payoutType: $payoutType),
        ));
    }

    /**
     * Sends the account holder the two-factor code a payout needs. Nothing is sent when the money
     * box has nothing to pay out, since that payout can't be made.
     *
     * @throws LocalizedException
     */
    public function requestCode(): TwoFactorToken
    {
        $available = $this->getAvailableBalance();
        if ($available !== null && $available <= 0) {
            throw new LocalizedException(__('The money box has nothing to pay out yet. No code was sent.'));
        }
        $token = $this->call(fn() => $this->polkurierClient->twoFactorToken()->twoFactorTokenRegister(
            new RegisterMessage(TwoFactorNamespaceEnum::MoneyBoxPayout),
        ));
        $this->apiEventDispatcher->dispatch(ApiEventEnum::TwoFactorCodeRequestAfter, null, [
            'namespace' => TwoFactorNamespaceEnum::MoneyBoxPayout,
        ]);

        return $token;
    }

    /**
     * Requests the payout. Moves money.
     *
     * @param string[] $orderNumbers empty pays out everything available
     * @param string|null $bankAccountNumber what a transfer goes to (chosenAccount())
     *
     * @throws LocalizedException when a transfer has no account, the code is missing, or Polkurier
     *     refuses
     */
    public function createPayout(
        array $orderNumbers,
        MoneyBoxPayoutTypeEnum $payoutType,
        MoneyBoxFeePaymentTypeEnum $feePaymentType,
        string $twoFactorToken,
        string $twoFactorCode,
        ?string $securityPin = null,
        ?string $bankAccountNumber = null,
    ): MoneyBoxPayoutMessage {
        if ($payoutType === MoneyBoxPayoutTypeEnum::Account && !$bankAccountNumber) {
            throw new LocalizedException(__('Enter the bank account the payout is transferred to.'));
        }
        if ($twoFactorToken === '' || $twoFactorCode === '') {
            throw new LocalizedException(__('Enter the confirmation code Polkurier sent you.'));
        }
        $message = $this->createMoneyBoxPayoutMessageBuilderFactory->create()
            ->orders(\array_values($orderNumbers))
            ->payoutType($payoutType)
            ->feePaymentType($feePaymentType)
            ->bankAccountNumber($bankAccountNumber ?: null)
            ->securityPin($securityPin ?: null)
            ->twoFactorToken($twoFactorToken)
            ->twoFactorCode($twoFactorCode)
            ->build();
        $payout = $this->call(fn() => $this->polkurierClient->moneyBox()->createPayout($message));
        $this->apiEventDispatcher->dispatch(ApiEventEnum::PayoutCreateAfter, null, [
            'order_numbers' => \array_values($orderNumbers),
            'payout_type' => $payoutType,
            'fee_payment_type' => $feePaymentType,
            'response' => $payout,
        ]);

        return $payout;
    }

    /**
     * @throws LocalizedException
     */
    public function cancelPayout(string $payoutNumber): MoneyBoxPayoutMessage
    {
        $request = new CancelMoneyBoxPayoutMessage($payoutNumber);
        $payout = $this->call(fn() => $this->polkurierClient->moneyBox()->cancelPayout($request));
        $this->apiEventDispatcher->dispatch(ApiEventEnum::PayoutCancelAfter, null, [
            'request' => $request,
            'response' => $payout,
        ]);

        return $payout;
    }

    /**
     * Polkurier order numbers typed one per line, or separated by commas or spaces.
     *
     * @return string[]
     */
    public static function parseOrderNumbers(string $text): array
    {
        return \array_values(\array_unique(\array_filter(\preg_split('/[\s,;]+/', \trim($text)) ?: [])));
    }

    /**
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     * @throws LocalizedException
     */
    private function call(callable $call): mixed
    {
        try {
            return $call();
        } catch (PolkurierException $e) {
            throw $this->errorMessageResolver->exception($e);
        }
    }
}
