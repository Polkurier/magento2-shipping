<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

use Polkurier\Shipping\Api\Data\PackTemplateInterface;

interface PackTemplateRepositoryInterface
{
    /**
     * @return iterable<PackTemplateInterface>
     */
    public function findAll(): iterable;

    /**
     * @param int $id
     *
     * @return PackTemplateInterface|null
     */
    public function findOneById(int $id): ?PackTemplateInterface;

    /**
     * @return PackTemplateInterface|null
     */
    public function findOneDefault(): ?PackTemplateInterface;

    /**
     * @param PackTemplateInterface $packTemplate
     *
     * @return PackTemplateInterface
     */
    public function save(PackTemplateInterface $packTemplate): PackTemplateInterface;

    /**
     * @param PackTemplateInterface $packTemplate
     *
     * @return void
     */
    public function delete(PackTemplateInterface $packTemplate): void;

    /**
     * @param int $id
     *
     * @return void
     */
    public function deleteById(int $id): void;
}
