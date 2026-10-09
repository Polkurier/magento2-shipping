<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\BankAccount;

use Magento\Framework\Api\SearchResults;
use Polkurier\Shipping\Api\Data\BankAccountSearchResultsInterface;

/**
 * The bank accounts found by search criteria.
 */
class BankAccountSearchResults extends SearchResults implements BankAccountSearchResultsInterface
{
}
