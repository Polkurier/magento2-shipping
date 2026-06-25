<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Quote\ShipmentData;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\Quote\ShipmentData;
use Polkurier\Shipping\Model\ResourceModel\Quote\ShipmentData as ShipmentDataResource;

class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ShipmentData::class, ShipmentDataResource::class);
    }
}
