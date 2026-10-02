<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Admin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\SyncEntityEnum;
use Polkurier\Shipping\Api\SynchronizationInterface;
use Polkurier\Shipping\Model\BankAccountFactory;
use Polkurier\Shipping\Model\ResourceModel\BankAccount as BankAccountResource;
use Polkurier\Shipping\Model\BankAccount\Confirmation;
use Polkurier\Shipping\Model\BankAccount\Publisher;
use Polkurier\Shipping\Model\Sync\SyncLock;
use Polkurier\Shipping\Model\Sync\SyncResult;

/**
 * Admin editing of bank accounts, which Polkurier changes only with the merchant's confirmation: a
 * save is sent with the code or PIN from the form, or waits in Magento for one, and a delete goes
 * to Polkurier first, with the PIN when the account locks bank accounts.
 */
class BankAccountHandler implements EntityHandlerInterface
{
    use EditsSyncableEntries;

    /**
     * The form field of the PIN a delete is confirmed with.
     */
    public const string CONFIRMATION_PIN = 'confirmation_pin';

    public function __construct(
        private readonly BankAccountRepositoryInterface $repository,
        private readonly BankAccountFactory $factory,
        private readonly Publisher $publisher,
        private readonly SynchronizationInterface $synchronization,
        private readonly DateTime $dateTime,
        private readonly SyncLock $syncLock,
        private readonly BankAccountResource $resource,
    ) {}

    /**
     * Form fields that confirm a change with Polkurier: used once, never stored or kept for the form.
     */
    public const array CONFIRMATION_FIELDS = [self::CONFIRMATION_PIN, 'two_factor_token', 'two_factor_code'];

    /**
     * Saves in Magento, then sends the account to Polkurier with the merchant's confirmation.
     * Without one, or when Polkurier refuses it, the account waits in Magento for a confirmation.
     *
     * @param array<string, mixed> $data
     */
    public function save(SyncableInterface $entity, array $data): SyncResult
    {
        $confirmation = Confirmation::fromForm($data);

        return $this->exclusively(function () use ($entity, $data, $confirmation): SyncResult {
            $result = new SyncResult(SyncEntityEnum::BankAccount);
            $this->applyFormData($entity, \array_diff_key($data, \array_flip(self::CONFIRMATION_FIELDS)));
            $entity->setSyncState(SyncableInterface::STATE_UNCONFIRMED);
            $this->store($entity);
            if (!$confirmation->isGiven()) {
                // It goes to Polkurier once confirmed with the code or the security PIN.
                $result->unconfirmed++;

                return $result;
            }
            /** @var BankAccountInterface $entity */
            try {
                $this->publisher->save($entity, $confirmation);
                $result->pushed++;
            } catch (LocalizedException $e) {
                $entity->setSyncError($e->getMessage());
                $this->store($entity);
                $result->failed++;
                $result->errors[] = $e->getMessage();
            }

            return $result;
        });
    }

    /**
     * Deletes in Polkurier, then in Magento. A PIN-locked account needs the PIN, which only the
     * bank account form can give.
     *
     * @param array<string, string> $confirmation the PIN under self::CONFIRMATION_PIN, if given
     */
    public function delete(SyncableInterface $entity, array $confirmation = []): SyncResult
    {
        $pin = \trim((string)($confirmation[self::CONFIRMATION_PIN] ?? '')) ?: null;

        return $this->exclusively(function () use ($entity, $pin): SyncResult {
            /** @var BankAccountInterface $entity */
            try {
                $this->publisher->delete($entity, $pin);
            } catch (LocalizedException $e) {
                throw new LocalizedException(\__(
                    'Polkurier did not delete bank account %1: %2 If your account locks bank accounts with a PIN, open it and delete it there with the PIN.',
                    $entity->getName(),
                    $e->getMessage(),
                ), $e);
            }
            $result = new SyncResult(SyncEntityEnum::BankAccount);
            $result->deleted++;

            return $result;
        });
    }

    public function get(int $id): SyncableInterface
    {
        return $this->repository->get($id);
    }

    public function create(): SyncableInterface
    {
        return $this->factory->create();
    }

    /**
     * @return string[]
     */
    public function synchronize(): array
    {
        return $this->synchronization->synchronize([SyncEntityEnum::BankAccount]);
    }

    private function store(SyncableInterface $entity): void
    {
        /** @var BankAccountInterface $entity */
        $this->repository->save($entity);
    }

    protected function assertUniqueName(SyncableInterface $entity): void
    {
        /** @var BankAccountInterface $entity */
        $id = $entity->getId() ? (int)$entity->getId() : null;
        if ($this->resource->isNameTaken($entity->getName(), $id)) {
            throw new LocalizedException(\__('A bank account named "%1" already exists. Choose another name.', $entity->getName()));
        }
    }

    public function getMenuId(): string
    {
        return 'Polkurier_Shipping::bank_accounts';
    }

    public function getLabel(): string
    {
        return (string)\__('Bank accounts');
    }

    public function getPersistorKey(): string
    {
        return 'polkurier_bank_account';
    }
}
