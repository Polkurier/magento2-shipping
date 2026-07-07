<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Repository\Order;

use Magento\Framework\Exception\AlreadyExistsException;
use Polkurier\Shipping\Api\Data\Order\AddressInterface;
use Polkurier\Shipping\Api\Order\AddressRepositoryInterface;
use Polkurier\Shipping\Model\Order\Address;
use Polkurier\Shipping\Model\Order\AddressFactory;
use Polkurier\Shipping\Model\ResourceModel\Order\Address as AddressResource;
use Polkurier\Shipping\Model\ResourceModel\Order\Address\CollectionFactory;

readonly class AddressRepository implements AddressRepositoryInterface
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
     * @param int $parentId
     *
     * @return AddressInterface|null
     */
    public function findOneByParentId(int $parentId): ?AddressInterface
    {
        $address = $this->entityFactory->create();
        $this->resource->load($address, $parentId, Address::FIELD_PARENT_ADDRESS_ID);

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
}
