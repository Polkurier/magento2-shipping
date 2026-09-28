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

/**
 * Sends bank account changes to Polkurier, with the confirmation Polkurier requires for them.
 *
 * Saving needs the security PIN when the account locks bank account edits, and otherwise a
 * two-factor code, one per save. Deleting needs the PIN when locked and nothing otherwise. None
 * of that can come from a background synchronization, so changes go out here, when the merchant
 * saves; a change that could not be sent stays "awaiting confirmation" until it is.
 */
class Publisher
{
    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly BankAccountMapper $mapper,
        private readonly BankAccountAdapter $adapter,
        private readonly SyncEngine $syncEngine,
        private readonly BankAccountRepositoryInterface $repository,
        private readonly ApiEventDispatcher $events,
        private readonly ErrorMessages $errorMessages,
        private readonly DateTime $dateTime,
    ) {}

    /**
     * Whether the account wants the security PIN, rather than a two-factor code, for bank accounts.
     *
     * @throws LocalizedException
     */
    public function isPinLocked(int $storeId): bool
    {
        try {
            return (bool)$this->clientPool->account($storeId)->getAccountInfo()->getPinLocks()?->isBankAccountsEdit();
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }
    }

    /**
     * Has Polkurier send the account holder the code a bank account save needs.
     *
     * @return string the two-factor token the save must carry together with the code
     * @throws LocalizedException
     */
    public function requestCode(int $storeId): string
    {
        try {
            $token = $this->clientPool->twoFactorToken($storeId)->twoFactorTokenRegister(
                new RegisterMessage(TwoFactorNamespaceEnum::ChangeIban),
            );
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }
        $this->events->dispatch(ApiEventEnum::TwoFactorCodeRequestAfter, $storeId, [
            'namespace' => TwoFactorNamespaceEnum::ChangeIban,
        ]);

        return $token->getId();
    }

    /**
     * Creates or updates the bank account on the account and records it as in sync.
     *
     * @throws LocalizedException when Polkurier refuses, e.g. a wrong code; the local entry is kept
     */
    public function save(BankAccountInterface $account, int $storeId, Confirmation $confirmation): void
    {
        $isNew = $account->getExtId() === null;
        try {
            $stored = $this->clientPool->bankAccounts($storeId)->saveBankAccount($this->mapper->toSaveMessage(
                $account,
                $confirmation->twoFactorToken,
                $confirmation->twoFactorCode,
                $confirmation->pin,
            ));
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }
        $this->syncEngine->pushed($this->adapter, $account, $this->mapper->normalize($stored), $this->now());
        $this->events->dispatch(ApiEventEnum::BankAccountSaveAfter, $storeId, [
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
    public function delete(BankAccountInterface $account, int $storeId, ?string $pin = null): void
    {
        if ($account->getExtId() !== null) {
            try {
                $this->clientPool->bankAccounts($storeId)->deleteBankAccount($this->mapper->toDeleteMessage($account, $pin));
            } catch (PolkurierException $e) {
                throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
            }
            $this->events->dispatch(ApiEventEnum::BankAccountDeleteAfter, $storeId, ['bank_account' => $account]);
        }
        $this->repository->delete($account);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . $this->dateTime->gmtTimestamp());
    }
}
