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

    private const string FAILURE_SUFFIX = '_failed';

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

    /**
     * Remembers for {@see self::FAILURE_LIFETIME} that asking Polkurier for the entry under $key
     * failed, and why. It's cleaned with $tag's entries and with all failures.
     */
    public function saveFailure(string $key, string $reason, CacheTagEnum $tag): void
    {
        $this->save(
            $reason,
            $key . self::FAILURE_SUFFIX,
            [$tag->value, CacheTagEnum::Failures->value],
            self::FAILURE_LIFETIME,
        );
    }

    /**
     * Why asking Polkurier for the entry under $key failed a moment ago, or null when it didn't.
     */
    public function loadFailure(string $key): ?string
    {
        $reason = $this->load($key . self::FAILURE_SUFFIX);

        return \is_string($reason) && $reason !== '' ? $reason : null;
    }
}
