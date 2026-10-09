<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\OrderPoint;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Model\OrderPoint as Model;
use Polkurier\Shipping\Model\ResourceModel\OrderPoint as ResourceModel;

/**
 * The orders' pickup points.
 *
 * @method Model[] getItems()
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = OrderPointInterface::FIELD_ORDER_ID;

    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
