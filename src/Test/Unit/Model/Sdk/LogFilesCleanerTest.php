<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Sdk\LogFilesCleaner;
use Polkurier\Shipping\Model\Sdk\LogFilesDirectory;
use Psr\Log\NullLogger;

#[CoversClass(LogFilesCleaner::class)]
class LogFilesCleanerTest extends TestCase
{
    private const int NOW = 1791200000;

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/polkurier-cleaner-' . \bin2hex(\random_bytes(4));
        \mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->directory . '/*') ?: [] as $file) {
            \unlink($file);
        }
        \rmdir($this->directory);
    }

    public function testOnlyTheSdksFilesOlderThanTheLifetimeAreDeleted(): void
    {
        $old = self::NOW - 8 * 86400;
        $this->file('20260927T101500-get_label-1a2b3c4d-file.pdf', $old);
        $this->file('20260927T101500-get_protocol-00ff00ff-protocol_711400-2.pdf', $old);
        $this->file('20261004T101500-get_label-1a2b3c4d-file.pdf', self::NOW - 86400);
        $this->file('shop-export.csv', $old);
        $this->file('20260927T101500-get_label-nothex00-file.pdf', $old);

        self::assertSame(2, $this->cleaner(7)->clean());
        self::assertSame([
            '20260927T101500-get_label-nothex00-file.pdf',
            '20261004T101500-get_label-1a2b3c4d-file.pdf',
            'shop-export.csv',
        ], $this->remaining());
    }

    public function testNothingIsDeletedWithoutALifetime(): void
    {
        $this->file('20260927T101500-get_label-1a2b3c4d-file.pdf', self::NOW - 400 * 86400);

        self::assertSame(0, $this->cleaner(0)->clean());
        self::assertCount(1, $this->remaining());
    }

    private function file(string $name, int $modifiedAt): void
    {
        \file_put_contents($this->directory . '/' . $name, 'x');
        \touch($this->directory . '/' . $name, $modifiedAt);
    }

    /**
     * @return string[]
     */
    private function remaining(): array
    {
        $names = \array_map('basename', \glob($this->directory . '/*') ?: []);
        \sort($names);

        return $names;
    }

    private function cleaner(int $days): LogFilesCleaner
    {
        $config = $this->createStub(Config::class);
        $config->method('getLogFilesLifetimeDays')->willReturn($days);
        $config->method('getLogFilesDirectory')->willReturn($this->directory);
        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(self::NOW);

        return new LogFilesCleaner($config, new LogFilesDirectory(new DirectoryList('/srv/magento')), $dateTime, new NullLogger());
    }
}
