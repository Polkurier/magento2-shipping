<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Enum\Adminhtml\FormMemoryEnum;

/**
 * What the admin screens of addresses, bank accounts and pack templates do with an entry. The
 * controllers of each kind are given its handler.
 */
interface EntityHandlerInterface
{
    /**
     * @throws NoSuchEntityException
     */
    public function get(int $id): SyncableInterface;

    public function create(): SyncableInterface;

    /**
     * Saves the form's fields and sends the entry to Polkurier.
     *
     * @param array<string, mixed> $data
     *
     * @return SyncResult what became of it in Polkurier, for the action's one message
     * @throws LocalizedException
     */
    public function save(SyncableInterface $entity, array $data): SyncResult;

    /**
     * @param string|null $pin the security PIN the merchant confirmed the delete with, for kinds
     *     Polkurier deletes only with one
     *
     * @return SyncResult what became of it in Polkurier, for the action's one message
     * @throws LocalizedException when it wasn't deleted
     */
    public function delete(SyncableInterface $entity, ?string $pin = null): SyncResult;

    /**
     * The full two-way synchronization of the kind.
     *
     * @return string[]
     * @throws LocalizedException
     */
    public function synchronize(): array;

    public function getLabel(): string;

    /**
     * Where the form's data waits when saving it failed.
     */
    public function getFormMemory(): FormMemoryEnum;
}
