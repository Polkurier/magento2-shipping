<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Order\Address;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\Order\Address;
use Polkurier\Shipping\Model\ResourceModel\Order\Address as AddressResource;
use Polkurier\Webservice\Enum\AddressTypeEnum;

class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Address::class, AddressResource::class);
    }

    /**
     * @param int $orderId
     *
     * @return $this
     */
    public function addOrderFilter(int $orderId): self
    {
        $this->addFieldToFilter('main_table.' . Address::FIELD_ORDER_ID, ['eq' => $orderId]);

        return $this;
    }

    /**
     * @param AddressTypeEnum $type
     *
     * @return $this
     */
    public function addTypeFilter(AddressTypeEnum $type): self
    {
        $this->addFieldToFilter('main_table.' . Address::FIELD_TYPE, ['eq' => $type->value]);

        return $this;
    }
}
