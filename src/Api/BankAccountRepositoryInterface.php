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
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\Data\BankAccountSearchResultsInterface;

/**
 * Stores the bank account records kept in Magento.
 *
 * @api
 */
interface BankAccountRepositoryInterface
{
    /**
     * @param int $id
     *
     * @return \Polkurier\Shipping\Api\Data\BankAccountInterface
     * @throws NoSuchEntityException
     */
    public function get(int $id): BankAccountInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     *
     * @return \Polkurier\Shipping\Api\Data\BankAccountSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): BankAccountSearchResultsInterface;

    /**
     * @param \Polkurier\Shipping\Api\Data\BankAccountInterface $entity
     *
     * @return \Polkurier\Shipping\Api\Data\BankAccountInterface
     * @throws CouldNotSaveException
     */
    public function save(BankAccountInterface $entity): BankAccountInterface;

    /**
     * @param \Polkurier\Shipping\Api\Data\BankAccountInterface $entity
     *
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(BankAccountInterface $entity): bool;

    /**
     * The bank accounts, without those deleted here and not synchronized yet: the default first,
     * then by name.
     *
     * @return \Polkurier\Shipping\Api\Data\BankAccountInterface[]
     */
    public function getAvailable(): array;

    /**
     * The account cash on delivery is paid out to unless the merchant picks another: the default
     * one, or else the first by name, since a shipment with cash on delivery needs an account.
     * Unlike the other defaults, it's null only when there is no account at all.
     *
     * @return \Polkurier\Shipping\Api\Data\BankAccountInterface|null
     */
    public function getPreferred(): ?BankAccountInterface;
}
