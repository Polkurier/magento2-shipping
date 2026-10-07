<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\Data\ShipmentSearchResultsInterface;
use Polkurier\Shipping\Api\Data\ShipmentSearchResultsInterfaceFactory;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ResourceModel;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\ShipmentFactory;

/**
 * Reads, lists, saves and deletes Polkurier shipments.
 */
readonly class ShipmentRepository implements ShipmentRepositoryInterface
{
    public function __construct(
        private ShipmentFactory $entityFactory,
        private ResourceModel $resource,
        private CollectionFactory $collectionFactory,
        private CollectionProcessorInterface $collectionProcessor,
        private ShipmentSearchResultsInterfaceFactory $searchResultsFactory,
    ) {}

    public function get(int $id): ShipmentInterface
    {
        $entity = $this->entityFactory->create();
        $this->resource->load($entity, $id);
        if (!$entity->getId()) {
            throw new NoSuchEntityException(__('The shipment with id "%1" does not exist.', $id));
        }

        return $entity;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): ShipmentSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function save(ShipmentInterface $entity): ShipmentInterface
    {
        try {
            /** @var Shipment $entity */
            $this->resource->save($entity);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the shipment: %1', $e->getMessage()), $e);
        }

        return $entity;
    }

    public function delete(ShipmentInterface $entity): bool
    {
        try {
            /** @var Shipment $entity */
            $this->resource->delete($entity);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the shipment: %1', $e->getMessage()), $e);
        }

        return true;
    }
}
