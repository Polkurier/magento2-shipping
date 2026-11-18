<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

/**
 * Polish time, which Polkurier's dates and times are in: statuses, deliveries, carrier scans,
 * collection dates and windows. The module stores status and delivery times in UTC.
 */
final class PolkurierTimeZoneProvider
{
    public const string ZONE = 'Europe/Warsaw';

    public static function getZone(): \DateTimeZone
    {
        return new \DateTimeZone(self::ZONE);
    }
}
