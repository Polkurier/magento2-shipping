<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\SearchResults;

use Magento\Framework\Api\SearchResults;
use Polkurier\Shipping\Api\Data\PackTemplateSearchResultsInterface;

/**
 * The pack templates found by search criteria.
 */
class PackTemplateSearchResults extends SearchResults implements PackTemplateSearchResultsInterface
{
}
