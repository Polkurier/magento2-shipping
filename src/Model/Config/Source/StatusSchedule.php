<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

/**
 * How often shipment statuses are updated, as the cron schedule of the job that does it
 * (etc/crontab.xml reads it from the setting).
 */
class StatusSchedule extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            '*/10 * * * *' => (string)\__('Every 10 minutes'),
            '*/15 * * * *' => (string)\__('Every 15 minutes'),
            '*/30 * * * *' => (string)\__('Every 30 minutes'),
            '0 * * * *' => (string)\__('Every hour'),
        ];
    }
}
