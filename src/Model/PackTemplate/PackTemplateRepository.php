<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\PackTemplate;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\PackTemplateSearchResultsInterface;
use Polkurier\Shipping\Api\Data\PackTemplateSearchResultsInterfaceFactory;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Model\PackTemplate;
use Polkurier\Shipping\Model\PackTemplateFactory;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate as ResourceModel;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\CollectionFactory;

/**
 * Reads, lists, saves and deletes pack templates.
 */
readonly class PackTemplateRepository implements PackTemplateRepositoryInterface
{
    public function __construct(
        private PackTemplateFactory $packTemplateFactory,
        private ResourceModel $resource,
        private CollectionFactory $collectionFactory,
        private CollectionProcessorInterface $collectionProcessor,
        private PackTemplateSearchResultsInterfaceFactory $searchResultsFactory,
    ) {}

    public function get(int $id): PackTemplateInterface
    {
        $entity = $this->packTemplateFactory->create();
        $this->resource->load($entity, $id);
        if (!$entity->getId()) {
            throw new NoSuchEntityException(__('The pack template with id "%1" does not exist.', $id));
        }

        return $entity;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): PackTemplateSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function save(PackTemplateInterface $entity): PackTemplateInterface
    {
        try {
            /** @var PackTemplate $entity */
            $this->resource->save($entity);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the pack template: %1', $e->getMessage()), $e);
        }

        return $entity;
    }

    public function delete(PackTemplateInterface $entity): bool
    {
        try {
            /** @var PackTemplate $entity */
            $this->resource->delete($entity);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the pack template: %1', $e->getMessage()), $e);
        }

        return true;
    }

    public function getAvailable(): array
    {
        return \array_values($this->collectionFactory->create()
            ->addFieldToFilter(SyncableInterface::FIELD_DELETED_AT, ['null' => true])
            ->setOrder(PackTemplateInterface::FIELD_IS_DEFAULT, 'DESC')
            ->setOrder(PackTemplateInterface::FIELD_NAME, 'ASC')
            ->getItems());
    }

    public function getDefault(): ?PackTemplateInterface
    {
        foreach ($this->getAvailable() as $template) {
            if ($template->isDefault()) {
                return $template;
            }
        }

        return null;
    }
}
