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
use Polkurier\Shipping\Model\BankAccount\Confirmation;
use Polkurier\Shipping\Model\BankAccount\Publisher;
use Polkurier\Shipping\Model\WebsiteScope;

class BankAccountHandler extends SyncableEntityHandler
{
    public function __construct(
        private readonly BankAccountRepositoryInterface $repository,
        private readonly BankAccountFactory $factory,
        private readonly Publisher $publisher,
        private readonly WebsiteScope $websiteScope,
        SynchronizationInterface $synchronization,
        DateTime $dateTime,
    ) {
        parent::__construct($synchronization, $dateTime);
    }

    /**
     * Form fields that confirm a change with Polkurier: used once, never stored or kept for the form.
     */
    public const array CONFIRMATION_FIELDS = ['confirmation_pin', 'two_factor_token', 'two_factor_code'];

    /**
     * Saves in Magento, then sends the account to Polkurier with the merchant's confirmation.
     * Without one, or when Polkurier refuses it, the account waits in Magento for a confirmation.
     *
     * @param array<string, mixed> $data
     * @return string[]
     */
    public function save(SyncableInterface $entity, array $data): array
    {
        $confirmation = Confirmation::fromForm($data);
        $this->applyFormData($entity, \array_diff_key($data, \array_flip(self::CONFIRMATION_FIELDS)));
        $entity->setSyncState(SyncableInterface::STATE_UNCONFIRMED);
        $this->store($entity);
        if (!$confirmation->isGiven()) {
            return [(string)\__('Saved in Magento. It goes to Polkurier once you confirm it with the code or your security PIN.')];
        }
        /** @var BankAccountInterface $entity */
        try {
            $this->publisher->save($entity, $this->websiteScope->getStoreId($entity->getWebsiteId()), $confirmation);
        } catch (LocalizedException $e) {
            $entity->setSyncError($e->getMessage());
            $this->store($entity);
        }

        return [];
    }

    /**
     * Deletes in Polkurier, then in Magento. A PIN-locked account needs the PIN, which only the
     * bank account form can give.
     *
     * @return string[]
     */
    public function delete(SyncableInterface $entity, ?string $pin = null): array
    {
        /** @var BankAccountInterface $entity */
        try {
            $this->publisher->delete($entity, $this->websiteScope->getStoreId($entity->getWebsiteId()), $pin);
        } catch (LocalizedException $e) {
            throw new LocalizedException(\__(
                'Polkurier did not delete bank account %1: %2 If your account locks bank accounts with a PIN, open it and delete it there with the PIN.',
                $entity->getName(),
                $e->getMessage(),
            ), $e);
        }

        return [];
    }

    public function get(int $id): SyncableInterface
    {
        return $this->repository->get($id);
    }

    public function create(): SyncableInterface
    {
        return $this->factory->create();
    }

    protected function store(SyncableInterface $entity): void
    {
        /** @var BankAccountInterface $entity */
        $this->repository->save($entity);
    }

    protected function remove(SyncableInterface $entity): void
    {
        /** @var BankAccountInterface $entity */
        $this->repository->delete($entity);
    }

    protected function getEntityType(): SyncEntityEnum
    {
        return SyncEntityEnum::BankAccount;
    }

    public function getAclResource(): string
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
