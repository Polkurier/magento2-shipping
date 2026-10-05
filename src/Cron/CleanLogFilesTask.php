<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Cron;

use Polkurier\Shipping\Model\Sdk\LogFilesCleaner;

readonly class CleanLogFilesTask
{
    public function __construct(
        private LogFilesCleaner $cleaner,
    ) {}

    public function execute(): void
    {
        $this->cleaner->clean();
    }
}
