<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Order\Cod\ReturnDetails;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\Order\Cod\ReturnDetails;
use Polkurier\Shipping\Model\ResourceModel\Order\Cod\ReturnDetails as ReturnDetailsResource;

class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ReturnDetails::class, ReturnDetailsResource::class);
    }

    /**
     * @param int $orderId
     *
     * @return $this
     */
    public function addOrderFilter(int $orderId): self
    {
        $this->addFieldToFilter('main_table.' . ReturnDetails::FIELD_ORDER_ID, ['eq' => $orderId]);

        return $this;
    }

    /**
     * @return $this
     */
    public function sortFromLatest(): self
    {
        $this->getSelect()->order('main_table.' . ReturnDetails::FIELD_DATE . ' ' . self::SORT_ORDER_DESC);

        return $this;
    }
}
