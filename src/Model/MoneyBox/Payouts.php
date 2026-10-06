<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\MoneyBox;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Enum\ApiEventEnum;
use Polkurier\Shipping\Model\ApiEventDispatcher;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxFeePaymentTypeEnum;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxPayoutTypeEnum;
use Polkurier\Webservice\Enum\TwoFactorToken\TwoFactorNamespaceEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Request\MoneyBox\CalculateMoneyBoxPayoutFeeMessage;
use Polkurier\Webservice\Request\MoneyBox\CancelMoneyBoxPayoutMessage;
use Polkurier\Webservice\Request\MoneyBox\CreateMoneyBoxPayoutMessage;
use Polkurier\Webservice\Request\MoneyBox\GetMoneyBoxPayoutsMessage;
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
readonly class Payouts
{
    public function __construct(
        private ClientPool $clientPool,
        private ErrorMessages $errorMessages,
        private ApiEventDispatcher $events,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function getConfig(): MoneyBoxConfigMessage
    {
        return $this->call(fn() => $this->clientPool->moneyBox()->getConfig());
    }

    /**
     * @throws LocalizedException
     */
    public function getPayouts(int $page = 1, int $pageSize = 20): MoneyBoxPayoutListMessage
    {
        return $this->call(fn() => $this->clientPool->moneyBox()->getPayouts(
            new GetMoneyBoxPayoutsMessage(pageSize: $pageSize, page: $page),
        ));
    }

    /**
     * What can be paid out now, in złoty, or null when Polkurier can't be asked.
     */
    public function getAvailableBalance(): ?float
    {
        try {
            return $this->call(fn(): float => $this->clientPool->account()->getAccountInfo()->getMoneyBoxPayoutAvailableBalance());
        } catch (LocalizedException) {
            return null;
        }
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
        return $this->call(fn() => $this->clientPool->moneyBox()->calculatePayoutFee(
            new CalculateMoneyBoxPayoutFeeMessage(orders: \array_values($orderNumbers), payoutType: $payoutType),
        ));
    }

    /**
     * Sends the account holder the two-factor code a payout needs; not when the money box has
     * nothing to pay out, for a payout that can't be made.
     *
     * @throws LocalizedException
     */
    public function requestCode(): TwoFactorToken
    {
        $available = $this->getAvailableBalance();
        if ($available !== null && $available <= 0) {
            throw new LocalizedException(\__('The money box has nothing to pay out yet. No code was sent.'));
        }
        $token = $this->call(fn() => $this->clientPool->twoFactorToken()->twoFactorTokenRegister(
            new RegisterMessage(TwoFactorNamespaceEnum::MoneyBoxPayout),
        ));
        $this->events->dispatch(ApiEventEnum::TwoFactorCodeRequestAfter, null, [
            'namespace' => TwoFactorNamespaceEnum::MoneyBoxPayout,
        ]);

        return $token;
    }

    /**
     * Requests the payout. Moves money.
     *
     * @param string[] $orderNumbers empty pays out everything available
     *
     * @throws LocalizedException
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
        $payout = $this->call(fn() => $this->clientPool->moneyBox()->createPayout(new CreateMoneyBoxPayoutMessage(
            orders: \array_values($orderNumbers),
            payoutType: $payoutType,
            feePaymentType: $feePaymentType,
            bankAccountNumber: $bankAccountNumber ?: null,
            securityPin: $securityPin ?: null,
            twoFactorToken: $twoFactorToken,
            twoFactorCode: $twoFactorCode,
        )));
        $this->events->dispatch(ApiEventEnum::PayoutCreateAfter, null, [
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
        $payout = $this->call(fn() => $this->clientPool->moneyBox()->cancelPayout($request));
        $this->events->dispatch(ApiEventEnum::PayoutCancelAfter, null, [
            'request' => $request,
            'response' => $payout,
        ]);

        return $payout;
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
            throw $this->errorMessages->exception($e);
        }
    }
}
