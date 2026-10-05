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
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\AddressSearchResultsInterface;
use Polkurier\Shipping\Api\Data\AddressSearchResultsInterfaceFactory;
use Polkurier\Shipping\Model\Address;
use Polkurier\Shipping\Model\AddressFactory;
use Polkurier\Shipping\Model\ResourceModel\Address as ResourceModel;
use Polkurier\Shipping\Model\ResourceModel\Address\CollectionFactory;

readonly class AddressRepository implements AddressRepositoryInterface
{
    public function __construct(
        private AddressFactory $entityFactory,
        private ResourceModel $resource,
        private CollectionFactory $collectionFactory,
        private CollectionProcessorInterface $collectionProcessor,
        private AddressSearchResultsInterfaceFactory $searchResultsFactory,
    ) {}

    public function get(int $id): AddressInterface
    {
        $entity = $this->entityFactory->create();
        $this->resource->load($entity, $id);
        if (!$entity->getId()) {
            throw new NoSuchEntityException(\__('The address with id "%1" does not exist.', $id));
        }

        return $entity;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): AddressSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function save(AddressInterface $entity): AddressInterface
    {
        try {
            /** @var Address $entity */
            $this->resource->save($entity);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(\__('Could not save the address: %1', $e->getMessage()), $e);
        }

        return $entity;
    }

    public function delete(AddressInterface $entity): bool
    {
        try {
            /** @var Address $entity */
            $this->resource->delete($entity);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(\__('Could not delete the address: %1', $e->getMessage()), $e);
        }

        return true;
    }
}
