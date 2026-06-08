<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\CarrierConfig;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig as CarrierConfigResource;
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(CarrierConfig::class, CarrierConfigResource::class);
    }

    /**
     * @return $this
     */
    protected function _afterLoad(): self
    {
        /** @var CarrierConfig $item */
        foreach ($this->getItems() as $item) {
            $this->getResource()->unserializeFields($item);
        }

        return parent::_afterLoad();
    }

    /**
     * @return $this
     */
    public function addActiveFilter(): self
    {
        return $this->addFieldToFilter(CarrierConfig::FIELD_ACTIVE, ['eq' => '1']);
    }

    /**
     * @return $this
     */
    public function sort(): self
    {
        $this->getSelect()->order(CarrierConfig::FIELD_SORT_ORDER . ' ' . self::SORT_ORDER_ASC);

        return $this;
    }
}
