<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\Data\ExternalEntityInterface;
use Polkurier\Shipping\Model\ResourceModel\BankAccount as BankAccountResource;

class BankAccount extends AbstractModel implements BankAccountInterface, ExternalEntityInterface
{
    public const string FIELD_ACCOUNT_ID = 'account_id';
    public const string FIELD_EXT_ID = 'ext_id';
    public const string FIELD_CREATED_AT = 'created_at';
    public const string FIELD_UPDATED_AT = 'updated_at';
    public const string FIELD_NAME = 'name';
    public const string FIELD_NUMBER = 'number';
    public const string FIELD_DEFAULT = 'default';

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(BankAccountResource::class);
    }

    /**
     * @return int|null
     */
    public function getAccountId(): ?int
    {
        return $this->getData(static::FIELD_ACCOUNT_ID);
    }

    /**
     * @param int|null $accountId
     *
     * @return void
     */
    public function setAccountId(?int $accountId): void
    {
        $this->setData(static::FIELD_ACCOUNT_ID, $accountId);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->getData(static::FIELD_NAME);
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setName(string $name): void
    {
        $this->setData(static::FIELD_NAME, $name);
    }

    /**
     * @return string
     */
    public function getNumber(): string
    {
        return $this->getData(static::FIELD_NUMBER);
    }

    /**
     * @param string $number
     *
     * @return void
     */
    public function setNumber(string $number): void
    {
        $this->setData(static::FIELD_NUMBER, $number);
    }

    /**
     * @return bool
     */
    public function isDefault(): bool
    {
        return $this->getData(static::FIELD_DEFAULT);
    }

    /**
     * @param bool $default
     *
     * @return void
     */
    public function setDefault(bool $default): void
    {
        $this->setData(static::FIELD_DEFAULT, $default);
    }

    /**
     * @return string|null
     */
    public function getExtId(): ?string
    {
        return $this->getData(static::FIELD_EXT_ID);
    }

    /**
     * @param string|null $extId
     *
     * @return void
     */
    public function setExtId(?string $extId): void
    {
        $this->setData(static::FIELD_EXT_ID, $extId);
    }

    /**
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->getData(static::FIELD_CREATED_AT);
    }

    /**
     * @param \DateTimeInterface $createdAt
     *
     * @return void
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->setData(static::FIELD_CREATED_AT, $createdAt);
    }

    /**
     * @return \DateTimeInterface
     */
    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->getData(static::FIELD_UPDATED_AT);
    }

    /**
     * @param \DateTimeInterface $updatedAt
     *
     * @return void
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->setData(static::FIELD_UPDATED_AT, $updatedAt);
    }
}
