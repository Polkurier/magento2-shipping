<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Admin;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Model\Sync\SyncLock;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\SyncEntityEnum;
use Polkurier\Shipping\Api\SynchronizationInterface;
use Polkurier\Shipping\Model\PackTemplateFactory;

class PackTemplateHandler extends SyncableEntityHandler
{
    public function __construct(
        private readonly PackTemplateRepositoryInterface $repository,
        private readonly PackTemplateFactory $factory,
        SynchronizationInterface $synchronization,
        DateTime $dateTime,
        SyncLock $syncLock,
    ) {
        parent::__construct($synchronization, $dateTime, $syncLock);
    }

    public function get(int $id): SyncableInterface
    {
        return $this->repository->get($id);
    }

    public function create(): SyncableInterface
    {
        return $this->factory->create();
    }

    protected function store(SyncableInterface $entity): void
    {
        /** @var PackTemplateInterface $entity */
        $this->repository->save($entity);
    }

    protected function remove(SyncableInterface $entity): void
    {
        /** @var PackTemplateInterface $entity */
        $this->repository->delete($entity);
    }

    protected function getEntityType(): SyncEntityEnum
    {
        return SyncEntityEnum::PackTemplate;
    }

    public function getMenuId(): string
    {
        return 'Polkurier_Shipping::pack_templates';
    }

    public function getLabel(): string
    {
        return (string)\__('Pack templates');
    }

    public function getPersistorKey(): string
    {
        return 'polkurier_pack_template';
    }
}
