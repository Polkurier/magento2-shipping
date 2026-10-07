<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\PackTemplate;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Enum\FormMemoryEnum;
use Polkurier\Shipping\Enum\SyncEntityEnum;
use Polkurier\Shipping\Model\PackTemplateFactory;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate as PackTemplateResource;
use Polkurier\Shipping\Model\Sync\SyncableEntityHandler;
use Polkurier\Shipping\Model\Sync\Synchronization;
use Polkurier\Shipping\Model\Sync\SyncLock;
use Polkurier\Shipping\Model\Sync\SyncResultFactory;

/**
 * The pack templates, as the admin creates, edits and deletes them.
 */
readonly class PackTemplateHandler extends SyncableEntityHandler
{
    public function __construct(
        private PackTemplateRepositoryInterface $repository,
        private PackTemplateFactory $factory,
        Synchronization $synchronization,
        DateTime $dateTime,
        SyncLock $syncLock,
        SyncResultFactory $syncResultFactory,
        private PackTemplateResource $resource,
    ) {
        parent::__construct($synchronization, $dateTime, $syncLock, $syncResultFactory);
    }

    public function get(int $id): SyncableInterface
    {
        return $this->repository->get($id);
    }

    public function create(): SyncableInterface
    {
        return $this->factory->create();
    }

    public function getMenuId(): string
    {
        return 'Polkurier_Shipping::pack_templates';
    }

    public function getLabel(): string
    {
        return (string)__('Pack templates');
    }

    public function getFormMemory(): FormMemoryEnum
    {
        return FormMemoryEnum::PackTemplate;
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

    protected function assertUniqueName(SyncableInterface $entity): void
    {
        /** @var PackTemplateInterface $entity */
        $id = $entity->getId() ? (int)$entity->getId() : null;
        if ($this->resource->isNameTaken($entity->getName(), $id)) {
            throw new LocalizedException(__(
                'A pack template named "%1" already exists. Choose another name.',
                $entity->getName(),
            ));
        }
    }
}
