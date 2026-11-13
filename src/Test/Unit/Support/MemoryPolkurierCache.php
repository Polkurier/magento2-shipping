<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;

/**
 * The module's cache, kept in memory, with each entry's lifetime and tags open to assertions.
 */
class MemoryPolkurierCache extends PolkurierCache
{
    /**
     * @var array<string, string> by key
     */
    public array $entries = [];

    /**
     * @var array<string, int|null> by key
     */
    public array $lifetimes = [];

    /**
     * @var array<string, string[]> by key
     */
    public array $tags = [];

    public function __construct() {}

    public function load($identifier): string|false
    {
        return $this->entries[$identifier] ?? false;
    }

    /**
     * @param string[] $tags
     */
    public function save($data, $identifier, array $tags = [], $lifeTime = null): bool
    {
        $this->entries[$identifier] = (string)$data;
        $this->lifetimes[$identifier] = $lifeTime;
        $this->tags[$identifier] = $tags;

        return true;
    }

    public function remove($identifier): bool
    {
        unset($this->entries[$identifier], $this->lifetimes[$identifier], $this->tags[$identifier]);

        return true;
    }

    /**
     * Removes every entry, or those with any of the tags given.
     *
     * @param string[] $tags
     */
    public function clean($mode = \Zend_Cache::CLEANING_MODE_ALL, array $tags = []): bool
    {
        foreach (\array_keys($this->entries) as $key) {
            if ($tags === [] || \array_intersect($tags, $this->tags[$key]) !== []) {
                $this->remove($key);
            }
        }

        return true;
    }
}
