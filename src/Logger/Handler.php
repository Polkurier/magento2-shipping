<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Writes the module's and the SDK's log records to monthly files in var/log/polkurier, Apache
 * style: requests and answers to e.g. 2026-10-access.log; failed requests, warnings and errors to
 * 2026-10-error.log. Each record goes to the files of its own month, so a process that runs into
 * the next month continues in that month's files.
 */
class Handler extends Base
{
    public const string DIRECTORY = 'var/log/polkurier';

    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    private string $directory;

    public function __construct(DriverInterface $filesystem, ?string $filePath = null)
    {
        $this->fileName = self::DIRECTORY . '/'
            . self::fileName(new \DateTimeImmutable('now', new \DateTimeZone('UTC')), false);
        parent::__construct($filesystem, $filePath);
        $this->directory = \dirname((string)$this->url);
        // Each record picks its file just before it's written. A processor rather than
        // write(), whose record is an array in Monolog 2 (Magento 2.4.7) and an object in Monolog 3.
        $this->pushProcessor($this->pickFile(...));
    }

    /**
     * The file a record goes to, e.g. "2026-10-access.log", or "2026-10-error.log" from a warning up.
     */
    public static function fileName(\DateTimeInterface $at, bool $error): string
    {
        return $at->format('Y-m') . ($error ? '-error.log' : '-access.log');
    }

    /**
     * @param LogRecord|array<string, mixed> $record
     *
     * @return LogRecord|array<string, mixed> the same record
     */
    private function pickFile(LogRecord|array $record): LogRecord|array
    {
        $at = $record['datetime'] ?? null;
        $level = $record['level'] ?? null;
        $url = $this->directory . '/' . self::fileName(
            $at instanceof \DateTimeInterface ? $at : new \DateTimeImmutable(),
            ($level instanceof \BackedEnum ? $level->value : (int)$level) >= Logger::WARNING,
        );
        if ($url !== $this->url) {
            $this->close();
            $this->url = $url;
        }

        return $record;
    }
}
