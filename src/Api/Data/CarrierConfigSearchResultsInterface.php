<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * The carrier configurations found by search criteria.
 *
 * @api
 */
interface CarrierConfigSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Polkurier\Shipping\Api\Data\CarrierConfigInterface[]
     */
    public function getItems();

    /**
     * @param \Polkurier\Shipping\Api\Data\CarrierConfigInterface[] $items
     *
     * @return $this
     */
    public function setItems(array $items);
}
