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
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigSearchResultsInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigSearchResultsInterfaceFactory;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\CarrierConfigFactory;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig as ResourceModel;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\CollectionFactory;

class CarrierConfigRepository implements CarrierConfigRepositoryInterface
{
    public function __construct(
        private readonly CarrierConfigFactory $entityFactory,
        private readonly ResourceModel $resource,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly CarrierConfigSearchResultsInterfaceFactory $searchResultsFactory,
    ) {}

    public function get(int $id): CarrierConfigInterface
    {
        $entity = $this->entityFactory->create();
        $this->resource->load($entity, $id);
        if (!$entity->getId()) {
            throw new NoSuchEntityException(\__('The carrier settings with id "%1" do not exist.', $id));
        }

        return $entity;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): CarrierConfigSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function save(CarrierConfigInterface $entity): CarrierConfigInterface
    {
        try {
            /** @var CarrierConfig $entity */
            $this->resource->save($entity);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(\__('Could not save the carrier settings: %1', $e->getMessage()), $e);
        }

        return $entity;
    }

    public function delete(CarrierConfigInterface $entity): bool
    {
        try {
            /** @var CarrierConfig $entity */
            $this->resource->delete($entity);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(\__('Could not delete the carrier settings: %1', $e->getMessage()), $e);
        }

        return true;
    }
}
