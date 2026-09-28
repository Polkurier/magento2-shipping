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
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Pricing\Fixed;
use Polkurier\Shipping\Model\Pricing\PricingModelEnum;
use Polkurier\Shipping\Model\WebsiteScope;
use Psr\Log\LoggerInterface as Logger;

/**
 * The carriers list in the admin: each website's carriers, narrowed to its available carriers.
 * Carriers left out keep their settings; they're only hidden.
 */
class Collection extends SearchResult
{
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        private readonly Config $config,
        private readonly WebsiteScope $websiteScope,
        $mainTable = 'polkurier_carrier_config',
        $resourceModel = \Polkurier\Shipping\Model\ResourceModel\CarrierConfig::class,
        $identifierName = null,
        $connectionName = null,
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $mainTable, $resourceModel, $identifierName, $connectionName);
    }

    /**
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $connection = $this->getConnection();
        foreach ($this->websiteScope->getWebsiteIds() as $websiteId) {
            $available = $this->config->getAvailableCarriers($this->websiteScope->getStoreId($websiteId));
            if ($available !== []) {
                $this->getSelect()->where(
                    $connection->quoteInto('main_table.website_id <> ?', $websiteId)
                    . ' OR ' . $connection->quoteInto('main_table.carrier_code IN (?)', $available),
                );
            }
        }

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
            $info = \json_decode((string)$item->getData('price_info'), true);
            $item->setData('price', $item->getData('pricing_model') === PricingModelEnum::Fixed->value && \is_array($info)
                ? ($info[Fixed::PRICE] ?? null)
                : null);
        }

        return $this;
    }
}
