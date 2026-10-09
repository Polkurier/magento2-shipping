<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\CarrierConfig;

use Magento\Framework\Api\SearchResults;
use Polkurier\Shipping\Api\Data\CarrierConfigSearchResultsInterface;

/**
 * The carrier configurations found by search criteria.
 */
class CarrierConfigSearchResults extends SearchResults implements CarrierConfigSearchResultsInterface
{
}
