<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\BankAccount;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\ApiEventEnum;
use Polkurier\Shipping\Model\ApiEventDispatcher;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Mapper\BankAccountMapper;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Shipping\Model\Sync\Adapter\BankAccountAdapter;
use Polkurier\Shipping\Model\Sync\SyncEngine;
use Polkurier\Webservice\Enum\TwoFactorToken\TwoFactorNamespaceEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Request\TwoFactorToken\RegisterMessage;
use Polkurier\Webservice\Response\TwoFactorToken;

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
        private ClientPool $clientPool,
        private BankAccountMapper $mapper,
        private BankAccountAdapter $adapter,
        private SyncEngine $syncEngine,
        private BankAccountRepositoryInterface $repository,
        private ApiEventDispatcher $events,
        private ErrorMessages $errorMessages,
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
            return (bool)$this->clientPool->account()->getAccountInfo()->getPinLocks()?->isBankAccountsEdit();
        } catch (PolkurierException $e) {
            throw $this->errorMessages->exception($e);
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
            $token = $this->clientPool->twoFactorToken()->twoFactorTokenRegister(
                new RegisterMessage(TwoFactorNamespaceEnum::ChangeBankAccountNumber),
            );
        } catch (PolkurierException $e) {
            throw $this->errorMessages->exception($e);
        }
        $this->events->dispatch(ApiEventEnum::TwoFactorCodeRequestAfter, null, [
            'namespace' => TwoFactorNamespaceEnum::ChangeBankAccountNumber,
        ]);

        return $token;
    }

    /**
     * Creates or updates the bank account on the account and records it as in sync.
     *
     * @throws LocalizedException when Polkurier refuses, e.g. a wrong code; the local entry is kept
     */
    public function save(BankAccountInterface $account, Confirmation $confirmation): void
    {
        $isNew = $account->getExtId() === null;
        try {
            $stored = $this->clientPool->bankAccounts()->saveBankAccount($this->mapper->toSaveMessage(
                $account,
                $confirmation->twoFactorToken,
                $confirmation->twoFactorCode,
                $confirmation->pin,
            ));
        } catch (PolkurierException $e) {
            throw $this->errorMessages->exception($e);
        }
        $this->syncEngine->pushed($this->adapter, $account, $this->mapper->normalize($stored), $this->now());
        $this->events->dispatch(ApiEventEnum::BankAccountSaveAfter, null, [
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
                $this->clientPool->bankAccounts()->deleteBankAccount($this->mapper->toDeleteMessage($account, $pin));
            } catch (PolkurierException $e) {
                throw $this->errorMessages->exception($e);
            }
            $this->events->dispatch(ApiEventEnum::BankAccountDeleteAfter, null, ['bank_account' => $account]);
        }
        $this->repository->delete($account);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . $this->dateTime->gmtTimestamp());
    }
}
