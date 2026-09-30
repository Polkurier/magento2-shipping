<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * A request that failed a moment ago and isn't sent again yet. The message is the original
 * failure, already described for the merchant.
 */
class RecentFailureException extends PolkurierException
{
}
