<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * A bank account cash on delivery is paid out to.
 */
interface BankAccountInterface extends SyncableInterface
{
    public const string NAME = 'name';
    public const string NUMBER = 'number';
    public const string IS_DEFAULT = 'is_default';

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): static;

    /**
     * @return string
     */
    public function getNumber(): string;

    /**
     * @param string $number
     * @return $this
     */
    public function setNumber(string $number): static;

    /**
     * @return bool
     */
    public function isDefault(): bool;

    /**
     * @param bool $default
     * @return $this
     */
    public function setDefault(bool $default): static;
}
