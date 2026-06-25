<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\CarrierConfig\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig as CarrierConfigResource;

class Collection extends SearchResult
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
    protected function _initSelect(): self
    {
        parent::_initSelect();
        $this->getSelect()->order([
            CarrierConfig::FIELD_ACTIVE . ' ' . self::SORT_ORDER_DESC,
            CarrierConfig::FIELD_SORT_ORDER . ' ' . self::SORT_ORDER_ASC,
        ]);

        return $this;
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
}
