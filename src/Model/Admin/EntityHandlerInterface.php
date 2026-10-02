<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Admin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Model\Sync\SyncResult;

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
     * @return SyncResult what became of it in Polkurier, for the action's one message
     * @throws LocalizedException
     */
    public function save(SyncableInterface $entity, array $data): SyncResult;

    /**
     * @param array<string, string> $confirmation what the merchant confirmed the delete with, such
     *     as the security PIN, for kinds Polkurier deletes only with one
     * @return SyncResult what became of it in Polkurier, for the action's one message
     * @throws LocalizedException when it wasn't deleted
     */
    public function delete(SyncableInterface $entity, array $confirmation = []): SyncResult;

    /**
     * The full two-way synchronization of the kind.
     *
     * @return string[]
     * @throws LocalizedException
     */
    public function synchronize(): array;

    /**
     * The menu item of the kind's screens.
     */
    public function getMenuId(): string;

    public function getLabel(): string;

    public function getPersistorKey(): string;
}
