<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * The Polkurier shipments found by search criteria.
 *
 * @api
 */
interface ShipmentSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Polkurier\Shipping\Api\Data\ShipmentInterface[]
     */
    public function getItems();

    /**
     * @param \Polkurier\Shipping\Api\Data\ShipmentInterface[] $items
     *
     * @return $this
     */
    public function setItems(array $items);
}
