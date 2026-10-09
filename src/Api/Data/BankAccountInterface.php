<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * A bank account cash on delivery is paid out to.
 *
 * @api
 */
interface BankAccountInterface extends SyncableInterface
{
    public const string FIELD_NUMBER = 'number';
    public const string FIELD_IS_DEFAULT = 'is_default';

    /**
     * @return string
     */
    public function getNumber(): string;

    /**
     * @param string $number
     *
     * @return $this
     */
    public function setNumber(string $number): static;

    /**
     * @return bool
     */
    public function isDefault(): bool;

    /**
     * @param bool $default
     *
     * @return $this
     */
    public function setDefault(bool $default): static;
}
