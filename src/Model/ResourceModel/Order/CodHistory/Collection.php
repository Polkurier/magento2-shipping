<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Order\CodHistory;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\Order\CodHistory;
use Polkurier\Shipping\Model\ResourceModel\Order\CodHistory as CodHistoryResource;

class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(CodHistory::class, CodHistoryResource::class);
    }
}
