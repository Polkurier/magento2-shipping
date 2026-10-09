<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\CarrierConfig;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\CarrierConfig as Model;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig as ResourceModel;

/**
 * The carriers' settings.
 *
 * @method Model[] getItems()
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = CarrierConfigInterface::FIELD_CONFIG_ID;

    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }

    /**
     * Attaches the store view labels to every loaded carrier in one query.
     */
    protected function _afterLoad(): static
    {
        parent::_afterLoad();
        /** @var ResourceModel $resource */
        $resource = $this->getResource();
        $labels = $resource->loadStoreLabels(\array_map('intval', \array_keys($this->_items)));
        foreach ($this->_items as $id => $item) {
            if ($item instanceof Model) {
                $resource->attachStoreLabels($item, $labels[(int)$id] ?? []);
            }
        }

        return $this;
    }
}
