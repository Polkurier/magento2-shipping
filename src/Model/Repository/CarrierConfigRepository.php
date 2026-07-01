<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Repository;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\CarrierConfigFactory;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig as CarrierConfigResource;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\CollectionFactory;

readonly class CarrierConfigRepository implements CarrierConfigRepositoryInterface
{
    /**
     * @param CarrierConfigFactory $entityFactory
     * @param CarrierConfigResource $resource
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        /**
         * @var CarrierConfigFactory
         */
        private CarrierConfigFactory $entityFactory,

        /**
         * @var CarrierConfigResource
         */
        private CarrierConfigResource $resource,

        /**
         * @var CollectionFactory
         */
        private CollectionFactory $collectionFactory,
    ) {}

    /**
     * @return iterable<CarrierConfigInterface>
     */
    public function findAll(): iterable
    {
        return $this->collectionFactory->create()->sort()->load();
    }

    /**
     * @return iterable<CarrierConfigInterface>
     */
    public function findActive(): iterable
    {
        return $this->collectionFactory->create()->addActiveFilter()->sort()->load();
    }

    /**
     * @param int $id
     *
     * @return CarrierConfigInterface|null
     */
    public function findOneById(int $id): ?CarrierConfigInterface
    {
        $carrierConfig = $this->entityFactory->create();
        $this->resource->load($carrierConfig, $id);

        return $carrierConfig->getId() ? $carrierConfig : null;
    }

    /**
     * @param string $code
     *
     * @return CarrierConfigInterface|null
     * @throws LocalizedException
     */
    public function findOneByCode(string $code): ?CarrierConfigInterface
    {
        $carrierConfig = $this->entityFactory->create();
        $this->resource->load($carrierConfig, $code, CarrierConfig::FIELD_CARRIER_CODE);

        return $carrierConfig->getId() ? $carrierConfig : null;
    }

    /**
     * @param CarrierConfigInterface $carrierConfig
     *
     * @return CarrierConfigInterface
     * @throws AlreadyExistsException
     */
    public function save(CarrierConfigInterface $carrierConfig): CarrierConfigInterface
    {
        $this->resource->save($carrierConfig);

        return $carrierConfig;
    }

    /**
     * @param CarrierConfigInterface $carrierConfig
     *
     * @return void
     * @throws \Exception
     */
    public function delete(CarrierConfigInterface $carrierConfig): void
    {
        $this->resource->delete($carrierConfig);
    }

    /**
     * @param string $code
     *
     * @return void
     * @throws LocalizedException
     * @throws \Exception
     */
    public function deleteByCode(string $code): void
    {
        $carrierConfig = $this->findOneByCode($code);
        if ($carrierConfig) {
            $this->delete($carrierConfig);
        }
    }
}
