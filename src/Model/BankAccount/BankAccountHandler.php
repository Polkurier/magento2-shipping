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
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Enum\FormMemoryEnum;
use Polkurier\Shipping\Enum\SyncEntityEnum;
use Polkurier\Shipping\Model\BankAccountFactory;
use Polkurier\Shipping\Model\ResourceModel\BankAccount as BankAccountResource;
use Polkurier\Shipping\Model\Sync\EditsSyncableEntriesTrait;
use Polkurier\Shipping\Model\Sync\EntityHandlerInterface;
use Polkurier\Shipping\Model\Sync\Synchronization;
use Polkurier\Shipping\Model\Sync\SyncLock;
use Polkurier\Shipping\Model\Sync\SyncResult;
use Polkurier\Shipping\Model\Sync\SyncResultFactory;

/**
 * Admin editing of bank accounts, which Polkurier changes only with the merchant's confirmation: a
 * save is sent with the code or PIN from the form, or waits in Magento for one, and a delete goes
 * to Polkurier first, with the PIN when the account locks bank accounts.
 */
readonly class BankAccountHandler implements EntityHandlerInterface
{
    use EditsSyncableEntriesTrait;

    /**
     * The form field of the PIN a delete is confirmed with.
     */
    public const string FIELD_CONFIRMATION_PIN = 'confirmation_pin';

    /**
     * Form fields that confirm a change with Polkurier: used once, never stored or kept for the form.
     */
    public const array CONFIRMATION_FIELDS = [self::FIELD_CONFIRMATION_PIN, 'two_factor_token', 'two_factor_code'];

    public function __construct(
        private BankAccountRepositoryInterface $repository,
        private BankAccountFactory $factory,
        private Publisher $publisher,
        private Synchronization $synchronization,
        private DateTime $dateTime,
        private SyncLock $syncLock,
        private BankAccountResource $resource,
        private SyncResultFactory $syncResultFactory,
    ) {}

    /**
     * Saves in Magento, then sends the account to Polkurier with the merchant's confirmation.
     * Without one, or when Polkurier refuses it, the account waits in Magento for a confirmation.
     *
     * @param array<string, mixed> $data
     */
    public function save(SyncableInterface $entity, array $data): SyncResult
    {
        $confirmation = Confirmation::fromForm($data);

        return $this->syncLock->run(function () use ($entity, $data, $confirmation): SyncResult {
            $result = $this->syncResultFactory->create(['entityType' => SyncEntityEnum::BankAccount]);
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
     * @param array<string, string> $confirmation the PIN under self::FIELD_CONFIRMATION_PIN, if given
     */
    public function delete(SyncableInterface $entity, array $confirmation = []): SyncResult
    {
        $pin = \trim((string)($confirmation[self::FIELD_CONFIRMATION_PIN] ?? '')) ?: null;

        return $this->syncLock->run(function () use ($entity, $pin): SyncResult {
            /** @var BankAccountInterface $entity */
            try {
                $this->publisher->delete($entity, $pin);
            } catch (LocalizedException $e) {
                throw new LocalizedException(__(
                    'Polkurier did not delete bank account %1: '
                    . '%2 If your account locks bank accounts with a PIN, open it and delete it there with the PIN.',
                    $entity->getName(),
                    $e->getMessage(),
                ), $e);
            }
            $result = $this->syncResultFactory->create(['entityType' => SyncEntityEnum::BankAccount]);
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

    public function getMenuId(): string
    {
        return 'Polkurier_Shipping::bank_accounts';
    }

    public function getLabel(): string
    {
        return (string)__('Bank accounts');
    }

    public function getFormMemory(): FormMemoryEnum
    {
        return FormMemoryEnum::BankAccount;
    }

    protected function assertUniqueName(SyncableInterface $entity): void
    {
        /** @var BankAccountInterface $entity */
        $id = $entity->getId() ? (int)$entity->getId() : null;
        if ($this->resource->isNameTaken($entity->getName(), $id)) {
            throw new LocalizedException(__(
                'A bank account named "%1" already exists. Choose another name.',
                $entity->getName(),
            ));
        }
    }

    private function store(SyncableInterface $entity): void
    {
        /** @var BankAccountInterface $entity */
        $this->repository->save($entity);
    }
}
