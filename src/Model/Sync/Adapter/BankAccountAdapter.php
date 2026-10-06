<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync\Adapter;

use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Enum\SyncEntityEnum;
use Polkurier\Shipping\Model\BankAccountFactory;
use Polkurier\Shipping\Model\Mapper\BankAccountMapper;
use Polkurier\Shipping\Model\ResourceModel\BankAccount\CollectionFactory;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Shipping\Model\Sync\AdapterInterface;

/**
 * Bank accounts come from Polkurier only: saving or deleting one there needs a two-factor code,
 * which a background synchronization cannot supply. Accounts added in Magento stay local, and are
 * still usable for cash on delivery, which carries the account number rather than an id.
 */
readonly class BankAccountAdapter implements AdapterInterface
{
    public function __construct(
        private ClientPool $clientPool,
        private BankAccountMapper $mapper,
        private CollectionFactory $collectionFactory,
        private BankAccountFactory $bankAccountFactory,
        private BankAccountRepositoryInterface $repository,
    ) {}

    public function getEntityType(): SyncEntityEnum
    {
        return SyncEntityEnum::BankAccount;
    }

    public function fetchRemote(): array
    {
        $remote = [];
        foreach ($this->clientPool->bankAccounts()->getBankAccounts() as $account) {
            if ($account->getId() !== null) {
                $remote[$account->getId()] = $this->mapper->normalize($account);
            }
        }

        return $remote;
    }

    public function fetchLocal(): array
    {
        return \array_values($this->collectionFactory->create()
            ->getItems());
    }

    public function createLocal(): SyncableInterface
    {
        return $this->bankAccountFactory->create();
    }

    public function applyRemote(SyncableInterface $local, array $remote): void
    {
        /** @var BankAccountInterface $local */
        $this->mapper->apply($remote, $local);
    }

    public function getNaturalKey(SyncableInterface|array $entry): ?string
    {
        /** @var BankAccountInterface|array<string, mixed> $entry */
        $value = \is_array($entry) ? (string)($entry['number'] ?? '') : $entry->getNumber();
        $key = \strtoupper((string)\preg_replace('/\s+/', '', $value));

        return $key !== '' ? $key : null;
    }

    public function save(SyncableInterface $local): void
    {
        /** @var BankAccountInterface $local */
        $this->repository->save($local);
    }

    public function delete(SyncableInterface $local): void
    {
        /** @var BankAccountInterface $local */
        $this->repository->delete($local);
    }
}
