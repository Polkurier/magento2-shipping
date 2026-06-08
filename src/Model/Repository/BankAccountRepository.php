<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Repository;

use Magento\Framework\Exception\AlreadyExistsException;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\ExternalEntityRepositoryInterface;
use Polkurier\Shipping\Model\BankAccount;
use Polkurier\Shipping\Model\BankAccountFactory;
use Polkurier\Shipping\Model\ResourceModel\BankAccount as BankAccountResource;
use Polkurier\Shipping\Model\ResourceModel\BankAccount\CollectionFactory;

readonly class BankAccountRepository implements BankAccountRepositoryInterface, ExternalEntityRepositoryInterface
{
    /**
     * @param BankAccountFactory $entityFactory
     * @param BankAccountResource $resource
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        /**
         * @var BankAccountFactory
         */
        private BankAccountFactory $entityFactory,

        /**
         * @var BankAccountResource
         */
        private BankAccountResource $resource,

        /**
         * @var CollectionFactory
         */
        private CollectionFactory $collectionFactory,
    ) {}

    /**
     * @return iterable<BankAccountInterface>
     */
    public function findAll(): iterable
    {
        return $this->collectionFactory->create()->load();
    }

    /**
     * @param int $id
     *
     * @return BankAccountInterface|null
     */
    public function findOneById(int $id): ?BankAccountInterface
    {
        $address = $this->entityFactory->create();
        $this->resource->load($address, $id);

        return $address->getId() ? $address : null;
    }

    /**
     * @param BankAccountInterface $bankAccount
     *
     * @return BankAccountInterface
     * @throws AlreadyExistsException
     */
    public function save(BankAccountInterface $bankAccount): BankAccountInterface
    {
        $this->resource->save($bankAccount);

        return $bankAccount;
    }

    /**
     * @param BankAccountInterface $bankAccount
     *
     * @return void
     * @throws \Exception
     */
    public function delete(BankAccountInterface $bankAccount): void
    {
        $this->resource->delete($bankAccount);
    }

    /**
     * @param int $id
     *
     * @return void
     * @throws \Exception
     */
    public function deleteById(int $id): void
    {
        $bankAccount = $this->findOneById($id);
        if ($bankAccount) {
            $this->delete($bankAccount);
        }
    }

    /**
     * @param string $extId
     *
     * @return BankAccountInterface|null
     */
    public function findOneByExtId(string $extId): ?BankAccountInterface
    {
        $bankAccount = $this->entityFactory->create();
        $this->resource->load($bankAccount, $extId, BankAccount::FIELD_EXT_ID);

        return $bankAccount->getId() ? $bankAccount : null;
    }
}
