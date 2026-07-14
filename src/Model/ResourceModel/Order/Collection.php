<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Order;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Shipping\Helper\CodHydrator;
use Polkurier\Shipping\Model\Order;
use Polkurier\Shipping\Model\Order\Cod;
use Polkurier\Shipping\Model\ResourceModel\Order as OrderResource;
use Polkurier\Shipping\Model\ResourceModel\Order\Cod as CodResource;
use Psr\Log\LoggerInterface;

class Collection extends AbstractCollection
{
    /**
     * @param CodHydrator $codHydrator
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param AdapterInterface|null $connection
     * @param AbstractDb|null $resource
     */
    public function __construct(
        private readonly CodHydrator $codHydrator,
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        ?AdapterInterface $connection = null,
        ?AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
    }

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Order::class, OrderResource::class);
    }

    /**
     * @return $this
     */
    protected function _initSelect(): static
    {
        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['cod' => $this->getConnection()->getTableName(CodResource::TABLE_NAME)],
            'main_table.' . Order::FIELD_ORDER_ID . ' = cod.' . Cod::FIELD_ORDER_ID,
            $this->getCodColumnsMap()
        );

        return $this;
    }

    /**
     * @return $this
     */
    protected function _afterLoad(): static
    {
        /** @var OrderInterface $order */
        foreach ($this->getItems() as $order) {
            $this->codHydrator->hydrateCod($order);
        }

        return parent::_afterLoad();
    }

    /**
     * @return array<string, string>
     */
    private function getCodColumnsMap(): array
    {
        $map = [];
        $columns = \array_keys($this->getConnection()->describeTable(
            $this->getConnection()->getTableName(CodResource::TABLE_NAME)
        ));
        foreach ($columns as $column) {
            $map[OrderResource::COD_FIELDS_PREFIX . $column] = $column;
        }

        return $map;
    }
}
