<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * Placing an order failed in a way that doesn't rule out that Polkurier placed it: no answer, an
 * answer that couldn't be read, or a refusal without a specific reason. The order must not be
 * sent again until it is reconciled.
 */
class PlacementUncertainException extends LocalizedException
{
}
