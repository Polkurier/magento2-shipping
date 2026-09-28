<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

/**
 * Writes the module's and the SDK's log records to var/log/polkurier.log.
 */
class Handler extends Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/polkurier.log';

    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;
}
