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
use Polkurier\Shipping\Api\SyncEntityEnum;
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
class BankAccountAdapter implements AdapterInterface
{
    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly BankAccountMapper $mapper,
        private readonly CollectionFactory $collectionFactory,
        private readonly BankAccountFactory $bankAccountFactory,
        private readonly BankAccountRepositoryInterface $repository,
    ) {}

    public function getEntityType(): SyncEntityEnum
    {
        return SyncEntityEnum::BankAccount;
    }

    public function fetchRemote(int $storeId): array
    {
        $remote = [];
        foreach ($this->clientPool->bankAccounts($storeId)->getBankAccounts() as $account) {
            if ($account->getId() !== null) {
                $remote[$account->getId()] = $this->mapper->normalize($account);
            }
        }

        return $remote;
    }

    public function fetchLocal(int $websiteId): array
    {
        return \array_values($this->collectionFactory->create()
            ->addFieldToFilter(SyncableInterface::WEBSITE_ID, ['eq' => $websiteId])
            ->getItems());
    }

    public function createLocal(int $websiteId): SyncableInterface
    {
        return $this->bankAccountFactory->create()->setWebsiteId($websiteId);
    }

    public function applyRemote(SyncableInterface $local, array $remote): void
    {
        /** @var BankAccountInterface $local */
        $this->mapper->apply($remote, $local);
    }

    public function canPush(): bool
    {
        return false;
    }

    public function push(SyncableInterface $local, int $storeId): array
    {
        throw new \LogicException('Bank accounts are not sent to Polkurier.');
    }

    public function deleteRemote(SyncableInterface $local, int $storeId): void
    {
        throw new \LogicException('Bank accounts are not deleted in Polkurier.');
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
