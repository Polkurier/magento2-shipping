<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Order\Pack;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\Order\Pack;
use Polkurier\Shipping\Model\ResourceModel\Order\Pack as PackResource;

class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Pack::class, PackResource::class);
    }

    /**
     * @param int $orderId
     *
     * @return $this
     */
    public function addOrderFilter(int $orderId): self
    {
        $this->addFieldToFilter(Pack::FIELD_ORDER_ID, ['eq' => $orderId]);

        return $this;
    }
}
