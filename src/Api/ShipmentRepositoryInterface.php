<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\Data\ShipmentSearchResultsInterface;

/**
 * Stores the Polkurier shipment records kept in Magento.
 *
 * @api
 */
interface ShipmentRepositoryInterface
{
    /**
     * @param int $id
     *
     * @return \Polkurier\Shipping\Api\Data\ShipmentInterface
     * @throws NoSuchEntityException
     */
    public function get(int $id): ShipmentInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     *
     * @return \Polkurier\Shipping\Api\Data\ShipmentSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ShipmentSearchResultsInterface;

    /**
     * @param \Polkurier\Shipping\Api\Data\ShipmentInterface $entity
     *
     * @return \Polkurier\Shipping\Api\Data\ShipmentInterface
     * @throws CouldNotSaveException
     */
    public function save(ShipmentInterface $entity): ShipmentInterface;

    /**
     * @param \Polkurier\Shipping\Api\Data\ShipmentInterface $entity
     *
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(ShipmentInterface $entity): bool;
}
