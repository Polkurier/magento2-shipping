<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum;

/**
 * The kinds of entries in the module's cache (Type), each under a tag of its own, so one kind can
 * be cleaned without the others. Every entry also carries the cache type's own tag.
 */
enum CacheTagEnum: string
{
    /**
     * The account's carriers and what each supports (Model\Carrier\CarrierCatalog).
     */
    case Carriers = 'POLKURIER_CARRIERS';

    /**
     * Pickup points looked up by id (CarrierCatalog::findPoint()).
     */
    case Points = 'POLKURIER_POINTS';

    /**
     * The pickup point map's token (Model\Checkout\MapTokenProvider).
     */
    case MapToken = 'POLKURIER_MAP_TOKEN';

    /**
     * Polkurier's notices about carriers (Model\Carrier\CarrierNotices).
     */
    case Notices = 'POLKURIER_NOTICES';

    /**
     * Answers about the account, such as whether bank accounts are locked (Model\Sdk\AccountCache).
     */
    case Account = 'POLKURIER_ACCOUNT';

    /**
     * Requests that failed a moment ago, not sent again for a while.
     */
    case Failures = 'POLKURIER_FAILURES';

    /**
     * What belongs to the Polkurier account and goes when the credentials change. Points are the
     * same whatever the account, and their entries are the account's by key anyway.
     *
     * @return self[]
     */
    public static function ofAccount(): array
    {
        return [self::Carriers, self::MapToken, self::Notices, self::Account, self::Failures];
    }
}
