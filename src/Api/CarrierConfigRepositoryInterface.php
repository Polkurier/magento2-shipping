<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

use Polkurier\Shipping\Api\Data\CarrierConfigInterface;

interface CarrierConfigRepositoryInterface
{
    /**
     * @return iterable<CarrierConfigInterface>
     */
    public function findAll(): iterable;

    /**
     * @return iterable<CarrierConfigInterface>
     */
    public function findActive(): iterable;

    /**
     * @param int $id
     *
     * @return CarrierConfigInterface|null
     */
    public function findOneById(int $id): ?CarrierConfigInterface;

    /**
     * @param string $code
     *
     * @return CarrierConfigInterface|null
     */
    public function findOneByCode(string $code): ?CarrierConfigInterface;

    /**
     * @param CarrierConfigInterface $carrierConfig
     *
     * @return CarrierConfigInterface
     */
    public function save(CarrierConfigInterface $carrierConfig): CarrierConfigInterface;

    /**
     * @param CarrierConfigInterface $carrierConfig
     *
     * @return void
     */
    public function delete(CarrierConfigInterface $carrierConfig): void;

    /**
     * @param string $code
     *
     * @return void
     */
    public function deleteByCode(string $code): void;
}
