<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Shipment;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ResourceModel;
use Polkurier\Shipping\Model\Shipment as Model;

/**
 * The Polkurier shipments.
 *
 * @method Model[] getItems()
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = ShipmentInterface::FIELD_ENTITY_ID;

    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
