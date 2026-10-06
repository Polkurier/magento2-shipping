<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\CarrierConfig;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\CarrierConfig as Model;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig as ResourceModel;

/**
 * The carriers' settings.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'config_id';

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

    /**
     * @return Model[]
     */
    public function getItems(): array
    {
        /** @var Model[] $items */
        $items = parent::getItems();

        return $items;
    }
}
