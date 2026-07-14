<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel;

use Magento\Framework\DB\Select;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Polkurier\Shipping\Helper\CodHydrator;
use Polkurier\Shipping\Model\Order as OrderModel;
use Polkurier\Shipping\Model\Order\Cod as CodModel;
use Polkurier\Shipping\Model\ResourceModel\Order\Cod;

class Order extends AbstractDb
{
    public const string TABLE_NAME = 'polkurier_order';
    public const string ID_FIELD = 'order_id';
    public const string COD_FIELDS_PREFIX = 'cod_';

    /**
     * @param CodHydrator $codHydrator
     * @param Context $context
     * @param string|null $connectionName
     */
    public function __construct(
        private readonly CodHydrator $codHydrator,
        Context $context,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(static::TABLE_NAME, static::ID_FIELD);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param AbstractModel $object
     *
     * @return Select
     */
    protected function _getLoadSelect($field, $value, $object): Select
    {
        $select = parent::_getLoadSelect($field, $value, $object);
        $select->joinLeft(
            ['cod' => $this->getConnection()->getTableName(Cod::TABLE_NAME)],
            'main_table.' . OrderModel::FIELD_ORDER_ID . ' = cod.' . CodModel::FIELD_ORDER_ID,
            $this->getCodColumnsMap(),
        );

        return $select;
    }

    /**
     * @param OrderModel $object
     *
     * @return $this
     */
    protected function _afterLoad(AbstractModel $object): static
    {
        $this->codHydrator->hydrateCod($object);

        return parent::_afterLoad($object);
    }

    /**
     * @return array<string, string>
     */
    private function getCodColumnsMap(): array
    {
        $map = [];
        $columns = \array_keys($this->getConnection()->describeTable(
            $this->getConnection()->getTableName(Cod::TABLE_NAME)
        ));
        foreach ($columns as $column) {
            $map[static::COD_FIELDS_PREFIX . $column] = $column;
        }

        return $map;
    }
}
