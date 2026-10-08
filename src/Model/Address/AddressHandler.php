<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Address;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Enum\Adminhtml\FormMemoryEnum;
use Polkurier\Shipping\Model\ResourceModel\Address as AddressResource;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sync\SyncableEntityHandler;
use Polkurier\Shipping\Model\Sync\SyncEngine;
use Polkurier\Shipping\Model\Sync\Synchronization;
use Polkurier\Shipping\Model\Sync\SyncLock;
use Polkurier\Shipping\Model\Sync\SyncResultFactory;

/**
 * The sender addresses, as the admin creates, edits and deletes them.
 */
readonly class AddressHandler extends SyncableEntityHandler
{
    public function __construct(
        private AddressRepositoryInterface $addressRepository,
        AddressSyncAdapter $addressSyncAdapter,
        SyncEngine $syncEngine,
        Synchronization $synchronization,
        ErrorMessageResolver $errorMessageResolver,
        DateTime $dateTime,
        SyncLock $syncLock,
        SyncResultFactory $syncResultFactory,
        private AddressResource $resource,
    ) {
        parent::__construct(
            $addressSyncAdapter,
            $syncEngine,
            $synchronization,
            $errorMessageResolver,
            $dateTime,
            $syncLock,
            $syncResultFactory,
        );
    }

    public function get(int $id): SyncableInterface
    {
        return $this->addressRepository->get($id);
    }

    public function getLabel(): string
    {
        return (string)__('Addresses');
    }

    public function getFormMemory(): FormMemoryEnum
    {
        return FormMemoryEnum::Address;
    }

    protected function assertUniqueName(SyncableInterface $entity): void
    {
        /** @var AddressInterface $entity */
        $id = $entity->getId() ? (int)$entity->getId() : null;
        if ($this->resource->isNameTaken($entity->getName(), $id)) {
            throw new LocalizedException(__(
                'An address labelled "%1" already exists. Choose another label.',
                $entity->getName(),
            ));
        }
    }
}
