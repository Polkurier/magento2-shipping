<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Polkurier\Shipping\Model\Config;

/**
 * The cache key of something kept for the Polkurier account: another account, or the sandbox,
 * never sees it, so changing the credentials can't serve the old account's answers.
 */
readonly class AccountCacheKey
{
    public function __construct(
        private Config $config,
    ) {}

    /**
     * @param string $name what is kept, e.g. "carriers"
     * @param string ...$variant anything else the entry depends on, such as the SDK's version
     */
    public function get(string $name, string ...$variant): string
    {
        $account = $this->config->getLogin() . '|' . (int)$this->config->isSandbox();

        return 'polkurier_' . $name . '_' . \sha1(\implode('|', [$account, ...$variant]));
    }
}
