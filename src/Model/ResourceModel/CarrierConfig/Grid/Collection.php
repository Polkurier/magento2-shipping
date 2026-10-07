<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\CarrierConfig\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Enum\PricingModelEnum;
use Polkurier\Shipping\Model\Carrier\AvailableCarrierFilter;
use Polkurier\Shipping\Model\Carrier\Pricing\FixedPriceCalculator;
use Psr\Log\LoggerInterface as Logger;

/**
 * The carriers list in the admin: the account's carriers, narrowed to the available carriers, and
 * always with the enabled carriers first, each part in the order checkout offers them, which the
 * merchant sets by dragging the rows.
 * Carriers left out, including those the account no longer offers, keep their settings; they're
 * only hidden.
 */
class Collection extends SearchResult
{
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        private readonly AvailableCarrierFilter $availableCarrierFilter,
        private readonly Json $json,
        $mainTable = 'polkurier_carrier_config',
        $resourceModel = \Polkurier\Shipping\Model\ResourceModel\CarrierConfig::class,
        $identifierName = null,
        $connectionName = null,
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName,
        );
    }

    /**
     * No other order: a view saved sorted by a column still gets the enabled carriers first, in
     * checkout order.
     *
     * @param mixed $field
     * @param mixed $direction
     *
     * @return $this
     */
    public function setOrder($field, $direction = self::SORT_ORDER_DESC)
    {
        return $this;
    }

    /**
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->where('main_table.available = ?', 1);
        $available = $this->availableCarrierFilter->getCodes();
        if ($available !== null) {
            $this->getSelect()->where('main_table.carrier_code IN (?)', $available);
        }
        $this->getSelect()->order([
            'main_table.active DESC',
            'main_table.sort_order ASC',
            'main_table.carrier_name ASC',
        ]);

        return $this;
    }

    /**
     * Shows a fixed price as a column of its own, for editing in the list.
     *
     * @return $this
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();
        foreach ($this->getItems() as $item) {
            try {
                $info = $this->json->unserialize((string)$item->getData(CarrierConfigInterface::FIELD_PRICE_INFO));
            } catch (\InvalidArgumentException) {
                // Prices the carrier's page can't read either: the grid shows none rather than fail.
                $info = null;
            }
            $fixed = $item->getData(CarrierConfigInterface::FIELD_PRICING_MODEL) === PricingModelEnum::Fixed->value;
            $item->setData(
                FixedPriceCalculator::FIELD_PRICE,
                $fixed && \is_array($info)
                    ? ($info[FixedPriceCalculator::FIELD_PRICE] ?? null)
                    : null,
            );
        }

        return $this;
    }
}
