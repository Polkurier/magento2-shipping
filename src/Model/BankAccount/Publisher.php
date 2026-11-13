<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\BankAccount;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Enum\Sdk\ApiEventEnum;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sync\SyncEngine;
use Polkurier\Webservice\Dto\Request\TwoFactorToken\RegisterMessage;
use Polkurier\Webservice\Dto\Response\TwoFactorToken;
use Polkurier\Webservice\Enum\TwoFactorToken\TwoFactorNamespaceEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Sends bank account changes to Polkurier, with the confirmation Polkurier requires for them.
 *
 * Saving needs the security PIN when the account locks bank account edits, and otherwise a
 * two-factor code, one per save. Deleting needs the PIN when locked and nothing otherwise. None
 * of that can come from a background synchronization, so changes go out here, when the merchant
 * saves; a change that could not be sent stays "awaiting confirmation" until it is.
 */
readonly class Publisher
{
    public function __construct(
        private PolkurierClientInterface $polkurierClient,
        private BankAccountMapper $bankAccountMapper,
        private BankAccountSyncAdapter $bankAccountSyncAdapter,
        private SyncEngine $syncEngine,
        private BankAccountRepositoryInterface $bankAccountRepository,
        private ApiEventDispatcher $apiEventDispatcher,
        private ErrorMessageResolver $errorMessageResolver,
        private DateTime $dateTime,
    ) {}

    /**
     * Whether the account wants the security PIN, rather than a two-factor code, for bank accounts.
     *
     * @throws LocalizedException
     */
    public function isPinLocked(): bool
    {
        try {
            return (bool)$this->polkurierClient->getAccount()->getAccountInfo()->pinLocks?->bankAccountsEdit;
        } catch (PolkurierException $e) {
            throw $this->errorMessageResolver->createException($e);
        }
    }

    /**
     * Has Polkurier send the account holder the code a bank account save needs.
     *
     * @return TwoFactorToken whose id the save must carry together with the code
     * @throws LocalizedException
     */
    public function requestCode(): TwoFactorToken
    {
        try {
            $token = $this->polkurierClient->getTwoFactorToken()->twoFactorTokenRegister(
                new RegisterMessage(TwoFactorNamespaceEnum::ChangeBankAccountNumber),
            );
        } catch (PolkurierException $e) {
            throw $this->errorMessageResolver->createException($e);
        }
        $this->apiEventDispatcher->dispatch(ApiEventEnum::TwoFactorCodeRequestAfter, null, [
            'namespace' => TwoFactorNamespaceEnum::ChangeBankAccountNumber,
        ]);

        return $token;
    }

    /**
     * Creates or updates the bank account on the account and records it as in sync.
     *
     * @throws LocalizedException when Polkurier refuses, e.g. a wrong code; the local entry is kept
     */
    public function save(
        BankAccountInterface $account,
        ?string $twoFactorToken,
        ?string $twoFactorCode,
        ?string $pin,
    ): void {
        $isNew = $account->getExtId() === null;
        try {
            $bankAccounts = $this->polkurierClient->getBankAccounts();
            $stored = $bankAccounts->saveBankAccount($this->bankAccountMapper->mapToSaveMessage(
                $account,
                $twoFactorToken,
                $twoFactorCode,
                $pin,
            ));
        } catch (PolkurierException $e) {
            throw $this->errorMessageResolver->createException($e);
        }
        $normalized = $this->bankAccountMapper->normalize($stored);
        $this->syncEngine->recordPushed($this->bankAccountSyncAdapter, $account, $normalized, $this->getNow());
        $this->apiEventDispatcher->dispatch(ApiEventEnum::BankAccountSaveAfter, null, [
            'bank_account' => $account,
            'is_new' => $isNew,
            'response' => $stored,
        ]);
    }

    /**
     * Deletes the bank account on the account, then in Magento.
     *
     * @throws LocalizedException when Polkurier refuses; the local entry is kept, marked as deleted
     */
    public function delete(BankAccountInterface $account, ?string $pin = null): void
    {
        if ($account->getExtId() !== null) {
            try {
                $message = $this->bankAccountMapper->mapToDeleteMessage($account, $pin);
                $this->polkurierClient->getBankAccounts()->deleteBankAccount($message);
            } catch (PolkurierException $e) {
                throw $this->errorMessageResolver->createException($e);
            }
            $this->apiEventDispatcher->dispatch(
                ApiEventEnum::BankAccountDeleteAfter,
                null,
                ['bank_account' => $account],
            );
        }
        $this->bankAccountRepository->delete($account);
    }

    private function getNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . $this->dateTime->gmtTimestamp());
    }
}
