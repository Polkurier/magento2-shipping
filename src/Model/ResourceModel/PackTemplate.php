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
use Polkurier\Shipping\Model\PackTemplate as PackTemplateModel;

class PackTemplate extends AbstractDb
{
    public const string TABLE_NAME = 'polkurier_pack_template';
    public const string ID_FIELD = 'template_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(static::TABLE_NAME, static::ID_FIELD);
    }

    /**
     * @param PackTemplateModel $object
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
     * @param int $templateId
     *
     * @return void
     * @throws LocalizedException
     */
    private function unsetOtherDefaults(int $templateId): void
    {
        $this->getConnection()->update(
            $this->getConnection()->getTableName(static::TABLE_NAME),
            [PackTemplateModel::FIELD_DEFAULT => 0],
            [$this->getIdFieldName() . ' != ?' => $templateId]
        );
    }
}
