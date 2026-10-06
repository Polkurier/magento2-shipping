<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * The pack templates found by search criteria.
 *
 * @api
 */
interface PackTemplateSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Polkurier\Shipping\Api\Data\PackTemplateInterface[]
     */
    public function getItems();

    /**
     * @param \Polkurier\Shipping\Api\Data\PackTemplateInterface[] $items
     *
     * @return $this
     */
    public function setItems(array $items);
}
