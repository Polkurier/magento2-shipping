<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Shipment;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ResourceModel;
use Polkurier\Shipping\Model\Shipment as Model;

/**
 * The Polkurier shipments.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }

    /**
     * @return Model[]
     */
    public function getItems(): array
    {
        /** @var Model[] $items */
        $items = parent::getItems();

        return $items;
    }
}
