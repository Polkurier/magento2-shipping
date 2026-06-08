<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

use Polkurier\Shipping\Api\Data\AddressInterface;

interface AddressRepositoryInterface
{
    /**
     * @return iterable<AddressInterface>
     */
    public function findAll(): iterable;

    /**
     * @param int $id
     *
     * @return AddressInterface|null
     */
    public function findOneById(int $id): ?AddressInterface;

    /**
     * @param AddressInterface $address
     *
     * @return AddressInterface
     */
    public function save(AddressInterface $address): AddressInterface;

    /**
     * @param AddressInterface $address
     *
     * @return void
     */
    public function delete(AddressInterface $address): void;

    /**
     * @param int $id
     *
     * @return void
     */
    public function deleteById(int $id): void;
}
