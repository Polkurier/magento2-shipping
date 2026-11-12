<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Logger;

use Magento\Framework\Filesystem\Driver\File;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Logger\Handler;

#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    private string $root;

    public function testRequestsAndErrorsGoToTheFilesOfTheirMonth(): void
    {
        $handler = new Handler(new File(), $this->root);

        $handler->handle($this->record('2026-09-30 23:59:59', 'last of September'));
        $handler->handle($this->record('2026-10-01 00:00:00', 'first of October'));
        $handler->handle($this->record('2026-10-06 07:00:00', 'refused in October', Level::Error));
        $handler->handle($this->record('2026-10-06 07:00:01', 'unknown value in October', Level::Warning));
        $handler->handle($this->record('2026-10-06 07:00:02', 'later in October'));
        $handler->close();

        self::assertSame(['2026-09-access.log', '2026-10-access.log', '2026-10-error.log'], $this->listFiles());
        self::assertSame(['last of September'], $this->readMessages('2026-09-access.log'));
        self::assertSame(['first of October', 'later in October'], $this->readMessages('2026-10-access.log'));
        self::assertSame(['refused in October', 'unknown value in October'], $this->readMessages('2026-10-error.log'));
    }

    protected function setUp(): void
    {
        $this->root = \sys_get_temp_dir() . '/polkurier-log-' . \bin2hex(\random_bytes(4)) . '/';
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->root . Handler::DIRECTORY . '/*') ?: [] as $file) {
            \unlink($file);
        }
        foreach ([Handler::DIRECTORY, 'var/log', 'var', ''] as $directory) {
            if (\is_dir($this->root . $directory)) {
                \rmdir($this->root . $directory);
            }
        }
    }

    /**
     * @return string[]
     */
    private function listFiles(): array
    {
        $files = \array_map('basename', \glob($this->root . Handler::DIRECTORY . '/*') ?: []);
        \sort($files);

        return $files;
    }

    /**
     * @return string[] the messages in a file, in order
     */
    private function readMessages(string $file): array
    {
        \preg_match_all(
            '/polkurier\.\w+: (.+?) \[\]/',
            (string)\file_get_contents($this->root . Handler::DIRECTORY . '/' . $file),
            $found,
        );

        return $found[1];
    }

    private function record(string $at, string $message, Level $level = Level::Info): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable($at, new \DateTimeZone('UTC')), 'polkurier', $level, $message);
    }
}
