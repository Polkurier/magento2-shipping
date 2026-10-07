<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Polkurier\Shipping\Api\Data\SyncableInterface;

/**
 * The admin list of entries kept in step with Polkurier, without those deleted: an entry deleted
 * in Magento stays until the deletion reaches Polkurier, and is left out everywhere meanwhile.
 */
class SyncableCollection extends SearchResult
{
    /**
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->where('main_table.' . SyncableInterface::FIELD_DELETED_AT . ' IS NULL');

        return $this;
    }
}
