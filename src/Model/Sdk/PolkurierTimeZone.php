<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

/**
 * Polish time, which Polkurier's dates and times are in: statuses, deliveries, carrier scans,
 * collection dates and windows. The module stores status and delivery times in UTC.
 */
final class PolkurierTimeZone
{
    public const string ZONE = 'Europe/Warsaw';

    public static function zone(): \DateTimeZone
    {
        return new \DateTimeZone(self::ZONE);
    }
}
