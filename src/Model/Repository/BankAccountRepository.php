<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Repository;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\Data\BankAccountSearchResultsInterface;
use Polkurier\Shipping\Api\Data\BankAccountSearchResultsInterfaceFactory;
use Polkurier\Shipping\Model\BankAccount;
use Polkurier\Shipping\Model\BankAccountFactory;
use Polkurier\Shipping\Model\ResourceModel\BankAccount as ResourceModel;
use Polkurier\Shipping\Model\ResourceModel\BankAccount\CollectionFactory;

class BankAccountRepository implements BankAccountRepositoryInterface
{
    public function __construct(
        private readonly BankAccountFactory $entityFactory,
        private readonly ResourceModel $resource,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly BankAccountSearchResultsInterfaceFactory $searchResultsFactory,
    ) {}

    public function get(int $id): BankAccountInterface
    {
        $entity = $this->entityFactory->create();
        $this->resource->load($entity, $id);
        if (!$entity->getId()) {
            throw new NoSuchEntityException(\__('The bank account with id "%1" does not exist.', $id));
        }

        return $entity;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): BankAccountSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function save(BankAccountInterface $entity): BankAccountInterface
    {
        try {
            /** @var BankAccount $entity */
            $this->resource->save($entity);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(\__('Could not save the bank account: %1', $e->getMessage()), $e);
        }

        return $entity;
    }

    public function delete(BankAccountInterface $entity): bool
    {
        try {
            /** @var BankAccount $entity */
            $this->resource->delete($entity);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(\__('Could not delete the bank account: %1', $e->getMessage()), $e);
        }

        return true;
    }
}
