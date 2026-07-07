<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Polkurier\Shipping\Model\BankAccount as BankAccountModel;

class BankAccount extends AbstractDb
{
    public const string TABLE_NAME = 'polkurier_bank_account';
    public const string ID_FIELD = 'account_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(static::TABLE_NAME, static::ID_FIELD);
    }

    /**
     * @param BankAccountModel $object
     *
     * @return $this
     * @throws LocalizedException
     */
    protected function _afterSave(AbstractModel $object): static
    {
        if ($object->isDefault()) {
            $this->unsetOtherDefaults((int)$object->getId());
        }

        return parent::_beforeSave($object);
    }

    /**
     * @param int $accountId
     *
     * @return void
     * @throws LocalizedException
     */
    private function unsetOtherDefaults(int $accountId): void
    {
        $this->getConnection()->update(
            $this->getConnection()->getTableName(static::TABLE_NAME),
            [BankAccountModel::FIELD_DEFAULT => 0],
            [$this->getIdFieldName() . ' != ?' => $accountId]
        );
    }
}
