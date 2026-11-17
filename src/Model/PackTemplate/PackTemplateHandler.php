<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\PackTemplate;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Enum\Adminhtml\FormMemoryEnum;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate as PackTemplateResource;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sync\SyncableEntityHandler;
use Polkurier\Shipping\Model\Sync\SyncEngine;
use Polkurier\Shipping\Model\Sync\SettingsSynchronizer;
use Polkurier\Shipping\Model\Sync\SyncLock;
use Polkurier\Shipping\Model\Sync\SyncResultFactory;

/**
 * The pack templates, as the admin creates, edits and deletes them.
 */
readonly class PackTemplateHandler extends SyncableEntityHandler
{
    public function __construct(
        private PackTemplateRepositoryInterface $packTemplateRepository,
        PackTemplateSyncAdapter $packTemplateSyncAdapter,
        SyncEngine $syncEngine,
        SettingsSynchronizer $settingsSynchronizer,
        ErrorMessageResolver $errorMessageResolver,
        DateTime $dateTime,
        SyncLock $syncLock,
        SyncResultFactory $syncResultFactory,
        private PackTemplateResource $resource,
    ) {
        parent::__construct(
            $packTemplateSyncAdapter,
            $syncEngine,
            $settingsSynchronizer,
            $errorMessageResolver,
            $dateTime,
            $syncLock,
            $syncResultFactory,
        );
    }

    public function get(int $id): SyncableInterface
    {
        return $this->packTemplateRepository->get($id);
    }

    public function getLabel(): string
    {
        return (string)__('Pack templates');
    }

    public function getFormMemory(): FormMemoryEnum
    {
        return FormMemoryEnum::PackTemplate;
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
