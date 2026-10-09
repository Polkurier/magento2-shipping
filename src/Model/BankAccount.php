<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Model\Sync\SyncableTrait;

/**
 * A bank account kept in Magento and mirrored from Polkurier.
 */
class BankAccount extends AbstractModel implements BankAccountInterface
{
    use SyncableTrait;

    public const string ID_FIELD = 'account_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_bank_account';

    /**
     * @var string
     */
    protected $_eventObject = 'bankAccount';

    public function getName(): string
    {
        $value = $this->getData(self::FIELD_NAME);

        return (string)$value;
    }

    public function setName(string $name): static
    {
        return $this->setData(self::FIELD_NAME, $name);
    }

    public function getNumber(): string
    {
        $value = $this->getData(self::FIELD_NUMBER);

        return (string)$value;
    }

    public function setNumber(string $number): static
    {
        return $this->setData(self::FIELD_NUMBER, $number);
    }

    public function isDefault(): bool
    {
        $value = $this->getData(self::FIELD_IS_DEFAULT);

        return (bool)$value;
    }

    public function setDefault(bool $default): static
    {
        return $this->setData(self::FIELD_IS_DEFAULT, (int)$default);
    }

    protected function _construct(): void
    {
        $this->_init(ResourceModel\BankAccount::class);
    }
}
