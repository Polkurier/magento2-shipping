<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Api\SearchResults;
use Polkurier\Shipping\Api\Data\ShipmentSearchResultsInterface;

/**
 * The Polkurier shipments found by search criteria.
 */
class ShipmentSearchResults extends SearchResults implements ShipmentSearchResultsInterface
{
}
