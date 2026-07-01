<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Repository;

use Magento\Framework\Exception\AlreadyExistsException;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\ExternalEntityRepositoryInterface;
use Polkurier\Shipping\Model\Address;
use Polkurier\Shipping\Model\AddressFactory;
use Polkurier\Shipping\Model\ResourceModel\Address as AddressResource;
use Polkurier\Shipping\Model\ResourceModel\Address\CollectionFactory;

readonly class AddressRepository implements AddressRepositoryInterface, ExternalEntityRepositoryInterface
{
    /**
     * @param AddressFactory $entityFactory
     * @param AddressResource $resource
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        /**
         * @var AddressFactory
         */
        private AddressFactory $entityFactory,

        /**
         * @var AddressResource
         */
        private AddressResource $resource,

        /**
         * @var CollectionFactory
         */
        private CollectionFactory $collectionFactory,
    ) {}

    /**
     * @return iterable<AddressInterface>
     */
    public function findAll(): iterable
    {
        return $this->collectionFactory->create()->load();
    }

    /**
     * @param int $id
     *
     * @return AddressInterface|null
     */
    public function findOneById(int $id): ?AddressInterface
    {
        $address = $this->entityFactory->create();
        $this->resource->load($address, $id);

        return $address->getId() ? $address : null;
    }

    /**
     * @return AddressInterface|null
     */
    public function findOneDefault(): ?AddressInterface
    {
        $address = $this->entityFactory->create();
        $this->resource->load($address, 1, Address::FIELD_DEFAULT);

        return $address->getId() ? $address : null;
    }

    /**
     * @param AddressInterface $address
     *
     * @return AddressInterface
     * @throws AlreadyExistsException
     */
    public function save(AddressInterface $address): AddressInterface
    {
        $this->resource->save($address);

        return $address;
    }

    /**
     * @param AddressInterface $address
     *
     * @return void
     * @throws \Exception
     */
    public function delete(AddressInterface $address): void
    {
        $this->resource->delete($address);
    }

    /**
     * @param int $id
     *
     * @return void
     * @throws \Exception
     */
    public function deleteById(int $id): void
    {
        $address = $this->findOneById($id);
        if ($address) {
            $this->delete($address);
        }
    }

    /**
     * @param string $extId
     *
     * @return AddressInterface|null
     */
    public function findOneByExtId(string $extId): ?AddressInterface
    {
        $address = $this->entityFactory->create();
        $this->resource->load($address, $extId, Address::FIELD_EXT_ID);

        return $address->getId() ? $address : null;
    }
}
