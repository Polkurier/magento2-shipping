<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * A synchronization in which every entity failed. Each failure was already logged where it
 * happened, and the message lists them.
 */
class SynchronizationFailedException extends LocalizedException
{
}
