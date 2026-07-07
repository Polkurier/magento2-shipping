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
    use ExternalEntityTrait;

    public const string FIELD_ACCOUNT_ID = 'account_id';
    public const string FIELD_NAME = 'name';
    public const string FIELD_NUMBER = 'number';
    public const string FIELD_DEFAULT = 'default';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_bank_account';

    /**
     * @var string
     */
    protected $_eventObject = 'polkurier_bank_account';

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
        return (int)$this->getData(static::FIELD_ACCOUNT_ID) ?: null;
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
        return (string)$this->getData(static::FIELD_NAME);
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
        return (string)$this->getData(static::FIELD_NUMBER);
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
        return (bool)$this->getData(static::FIELD_DEFAULT);
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
}
