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
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigSearchResultsInterface;

/**
 * Stores the carrier configuration records kept in Magento.
 *
 * @api
 */
interface CarrierConfigRepositoryInterface
{
    /**
     * @param int $id
     *
     * @return \Polkurier\Shipping\Api\Data\CarrierConfigInterface
     * @throws NoSuchEntityException
     */
    public function get(int $id): CarrierConfigInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     *
     * @return \Polkurier\Shipping\Api\Data\CarrierConfigSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): CarrierConfigSearchResultsInterface;

    /**
     * @param \Polkurier\Shipping\Api\Data\CarrierConfigInterface $entity
     *
     * @return \Polkurier\Shipping\Api\Data\CarrierConfigInterface
     * @throws CouldNotSaveException
     */
    public function save(CarrierConfigInterface $entity): CarrierConfigInterface;

    /**
     * @param \Polkurier\Shipping\Api\Data\CarrierConfigInterface $entity
     *
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(CarrierConfigInterface $entity): bool;
}
