<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\PackTemplateSearchResultsInterface;

/**
 * Stores the pack template records kept in Magento.
 *
 * @api
 */
interface PackTemplateRepositoryInterface
{
    /**
     * @param int $id
     *
     * @return \Polkurier\Shipping\Api\Data\PackTemplateInterface
     * @throws NoSuchEntityException
     */
    public function get(int $id): PackTemplateInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     *
     * @return \Polkurier\Shipping\Api\Data\PackTemplateSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): PackTemplateSearchResultsInterface;

    /**
     * @param \Polkurier\Shipping\Api\Data\PackTemplateInterface $entity
     *
     * @return \Polkurier\Shipping\Api\Data\PackTemplateInterface
     * @throws CouldNotSaveException
     */
    public function save(PackTemplateInterface $entity): PackTemplateInterface;

    /**
     * @param \Polkurier\Shipping\Api\Data\PackTemplateInterface $entity
     *
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(PackTemplateInterface $entity): bool;
}
