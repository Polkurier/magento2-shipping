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
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\AddressSearchResultsInterface;

/**
 * Stores the address records kept in Magento.
 *
 * @api
 */
interface AddressRepositoryInterface
{
    /**
     * @param int $id
     *
     * @return \Polkurier\Shipping\Api\Data\AddressInterface
     * @throws NoSuchEntityException
     */
    public function get(int $id): AddressInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     *
     * @return \Polkurier\Shipping\Api\Data\AddressSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): AddressSearchResultsInterface;

    /**
     * @param \Polkurier\Shipping\Api\Data\AddressInterface $entity
     *
     * @return \Polkurier\Shipping\Api\Data\AddressInterface
     * @throws CouldNotSaveException
     */
    public function save(AddressInterface $entity): AddressInterface;

    /**
     * @param \Polkurier\Shipping\Api\Data\AddressInterface $entity
     *
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(AddressInterface $entity): bool;

    /**
     * The sender addresses, without those deleted here and not synchronized yet: the default
     * first, then by name.
     *
     * @param bool $coverOnly only those that can be printed on the label as the sender
     *
     * @return \Polkurier\Shipping\Api\Data\AddressInterface[]
     */
    public function getSenderAddresses(bool $coverOnly = false): array;

    /**
     * The sender address marked as the default, if one is.
     *
     * @return \Polkurier\Shipping\Api\Data\AddressInterface|null
     */
    public function getDefaultSenderAddress(): ?AddressInterface;
}
