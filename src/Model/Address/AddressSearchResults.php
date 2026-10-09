<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Address;

use Magento\Framework\Api\SearchResults;
use Polkurier\Shipping\Api\Data\AddressSearchResultsInterface;

/**
 * The addresses found by search criteria.
 */
class AddressSearchResults extends SearchResults implements AddressSearchResultsInterface
{
}
