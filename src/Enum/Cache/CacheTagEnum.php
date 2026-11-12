<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum\Cache;

/**
 * The kinds of entries in the module's cache ({@see \Polkurier\Shipping\Model\Cache\Type}), each
 * under a tag of its own, so one kind can be cleaned without the others. Every entry also carries
 * the cache type's own tag.
 */
enum CacheTagEnum: string
{
    /**
     * The account's carriers and what each supports
     * ({@see \Polkurier\Shipping\Model\Carrier\CarrierCatalog}).
     */
    case Carriers = 'POLKURIER_CARRIERS';

    /**
     * Pickup points looked up by id
     * ({@see \Polkurier\Shipping\Model\Carrier\CarrierCatalog::findPoint()}).
     */
    case Points = 'POLKURIER_POINTS';

    /**
     * The pickup point map's token ({@see \Polkurier\Shipping\Model\PickupPoint\MapTokenProvider}).
     */
    case MapToken = 'POLKURIER_MAP_TOKEN';

    /**
     * Polkurier's notices about carriers
     * ({@see \Polkurier\Shipping\Model\Carrier\CarrierNoticeProvider}).
     */
    case Notices = 'POLKURIER_NOTICES';

    /**
     * Answers about the account, such as whether bank accounts are locked
     * ({@see \Polkurier\Shipping\Model\Sdk\AccountCache}).
     */
    case Account = 'POLKURIER_ACCOUNT';

    /**
     * Requests that failed a moment ago, not sent again for a while.
     */
    case Failures = 'POLKURIER_FAILURES';

    /**
     * The kinds that belong to the Polkurier account and are cleaned when the credentials change.
     * Pickup points aren't among them: they're the same for every account, and their keys include
     * the account anyway.
     *
     * @return self[]
     */
    public static function getAccountTags(): array
    {
        return [self::Carriers, self::MapToken, self::Notices, self::Account, self::Failures];
    }
}
