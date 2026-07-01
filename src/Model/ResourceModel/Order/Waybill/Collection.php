<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Order\Waybill;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\Order\Waybill;
use Polkurier\Shipping\Model\ResourceModel\Order\Waybill as WaybillResource;

class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Waybill::class, WaybillResource::class);
    }

    /**
     * @param int $orderId
     *
     * @return $this
     */
    public function addOrderFilter(int $orderId): self
    {
        $this->addFieldToFilter(Waybill::FIELD_ORDER_ID, ['eq' => $orderId]);

        return $this;
    }

    /**
     * @return $this
     */
    public function sortDefault(): self
    {
        $this->getSelect()->order(Waybill::FIELD_NUMBER . ' ' . static::SORT_ORDER_DESC);

        return $this;
    }
}
