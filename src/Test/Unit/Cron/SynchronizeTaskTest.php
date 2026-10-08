<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Cron;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Cron\SynchronizeTask;
use Polkurier\Shipping\Exception\SynchronizationFailedException;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sync\Synchronization;

#[CoversClass(SynchronizeTask::class)]
class SynchronizeTaskTest extends TestCase
{
    /** @var array<int, array{string, string, array<string, mixed>}> */
    private array $logged = [];

    public function testAFailureLoggedWhereItHappenedIsNotLoggedAgain(): void
    {
        $this->runFailing(new SynchronizationFailedException(__('Addresses: refused.')));

        self::assertSame([], $this->warnings());
    }

    public function testAnyOtherFailureIsLoggedWithItsException(): void
    {
        $failure = new LocalizedException(__('A synchronization with Polkurier is already running.'));
        $this->runFailing($failure);

        self::assertCount(1, $this->warnings());
        self::assertSame($failure, $this->warnings()[0][2]['exception']);
    }

    private function runFailing(\Throwable $failure): void
    {
        $synchronization = $this->createStub(Synchronization::class);
        $synchronization->method('synchronize')->willThrowException($failure);
        $this->task($synchronization)->execute();
    }

    private function task(Synchronization $synchronization): SynchronizeTask
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isSyncEnabled')->willReturn(true);
        $config->method('hasCredentials')->willReturn(true);
        $logger = $this->createStub(Logger::class);
        $logger->method('warning')
            ->willReturnCallback(function (string|\Stringable $message, array $context = []): void {
                $this->logged[] = ['warning', (string)$message, $context];
            });

        return new SynchronizeTask($synchronization, $config, $logger);
    }

    /**
     * @return array<int, array{string, string, array<string, mixed>}>
     */
    private function warnings(): array
    {
        return \array_values(\array_filter($this->logged, static fn(array $entry): bool => $entry[0] === 'warning'));
    }
}
