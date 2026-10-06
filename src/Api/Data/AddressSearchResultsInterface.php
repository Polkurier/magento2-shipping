<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * The addresses found by search criteria.
 *
 * @api
 */
interface AddressSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Polkurier\Shipping\Api\Data\AddressInterface[]
     */
    public function getItems();

    /**
     * @param \Polkurier\Shipping\Api\Data\AddressInterface[] $items
     *
     * @return $this
     */
    public function setItems(array $items);
}
