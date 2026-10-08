<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Logger;

use Monolog\Handler\HandlerInterface;
use Monolog\Logger as MonologLogger;
use Monolog\LoggerFactory as MonologLoggerFactory;
use Psr\Log\AbstractLogger;

/**
 * The module's log: var/log/polkurier, with API tokens masked. Its handler and processor are set in
 * etc/di.xml. It hands records to a plain Monolog logger of its own: Magento's logger type, or a
 * class extending it, would also get Magento's handlers and copy every record into system.log.
 */
class Logger extends AbstractLogger
{
    private readonly MonologLogger $monologLogger;

    /**
     * @param HandlerInterface[] $handlers
     * @param callable[] $processors
     */
    public function __construct(
        MonologLoggerFactory $monologLoggerFactory,
        array $handlers = [],
        array $processors = [],
    ) {
        $this->monologLogger = $monologLoggerFactory->create([
            'name' => 'polkurier',
            'handlers' => \array_values($handlers),
            'processors' => \array_values($processors),
        ]);
    }

    /**
     * @param mixed $level
     * @param array<string, mixed> $context
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->monologLogger->log($level, $message, $context);
    }
}
