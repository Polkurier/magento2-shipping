<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;
use Polkurier\Shipping\Enum\Cache\CacheTagEnum;

/**
 * The module's cache, on a line of its own under System > Cache Management. It holds the account's
 * carriers, pickup points, the map token, Polkurier's notices and what failed a moment ago. When
 * it's switched off there, nothing is kept and Polkurier is asked every time.
 *
 * Every entry also carries the tag of its kind ({@see CacheTagEnum}), so one kind can be cleaned
 * without the others.
 */
class Type extends TagScope
{
    public const string TYPE_IDENTIFIER = 'polkurier';
    public const string CACHE_TAG = 'POLKURIER';

    /**
     * How long, in seconds, a failure to ask Polkurier is remembered: meanwhile it isn't asked again.
     */
    public const int FAILURE_LIFETIME = 90;

    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }

    /**
     * Removes the entries of any of the kinds given.
     */
    public function cleanTags(CacheTagEnum ...$tags): bool
    {
        return $this->clean(
            \Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG,
            \array_map(static fn(CacheTagEnum $tag): string => $tag->value, $tags),
        );
    }
}
