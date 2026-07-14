<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Polkurier\Shipping\Model\CarrierConfig as CarrierConfigModel;

class CarrierConfig extends AbstractDb
{
    public const string TABLE_NAME = 'polkurier_carrier_config';
    public const string ID_FIELD = 'config_id';

    /**
     * @var array<string, array>
     */
    protected $_serializableFields = [
        CarrierConfigModel::FIELD_PRICE_INFO => [null, []],
        CarrierConfigModel::FIELD_ADDITIONAL_FIELDS => [null, []],
    ];

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(static::TABLE_NAME, static::ID_FIELD);
    }

    /**
     * @param string $code
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function loadByCode(string $code)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where(CarrierConfigModel::FIELD_CARRIER_CODE . ' = ?', $code)
            ->limit(1);

        return $connection->fetchRow($select);
    }
}
