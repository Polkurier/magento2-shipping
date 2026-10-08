<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Setup\Patch\Data;

use Magento\Framework\App\Cache\Manager;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Polkurier\Shipping\Model\Cache\Type;

/**
 * Switches the module's cache type on once, when the module is installed. Magento leaves a cache
 * type it hasn't seen before switched off, and without it the carrier list would be fetched from
 * Polkurier on every page that shows carriers. The merchant can switch it off afterwards.
 */
readonly class EnableCacheTypePatch implements DataPatchInterface
{
    public function __construct(
        private Manager $cacheManager,
    ) {}

    public function apply(): static
    {
        $this->cacheManager->setEnabled([Type::TYPE_IDENTIFIER], true);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
