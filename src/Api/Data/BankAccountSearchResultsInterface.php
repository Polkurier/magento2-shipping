<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * The bank accounts found by search criteria.
 *
 * @api
 */
interface BankAccountSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Polkurier\Shipping\Api\Data\BankAccountInterface[]
     */
    public function getItems();

    /**
     * @param \Polkurier\Shipping\Api\Data\BankAccountInterface[] $items
     *
     * @return $this
     */
    public function setItems(array $items);
}
