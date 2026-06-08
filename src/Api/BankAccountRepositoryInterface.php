<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

use Polkurier\Shipping\Api\Data\BankAccountInterface;

interface BankAccountRepositoryInterface
{
    /**
     * @return iterable<BankAccountInterface>
     */
    public function findAll(): iterable;

    /**
     * @param int $id
     *
     * @return BankAccountInterface|null
     */
    public function findOneById(int $id): ?BankAccountInterface;

    /**
     * @param BankAccountInterface $bankAccount
     *
     * @return BankAccountInterface
     */
    public function save(BankAccountInterface $bankAccount): BankAccountInterface;

    /**
     * @param BankAccountInterface $bankAccount
     *
     * @return void
     */
    public function delete(BankAccountInterface $bankAccount): void;

    /**
     * @param int $id
     *
     * @return void
     */
    public function deleteById(int $id): void;
}
