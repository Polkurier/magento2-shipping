<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Cron;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Cron\RefreshCarriersTask;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigSynchronizer;
use Polkurier\Shipping\Model\Config\ConfigProvider;

#[CoversClass(RefreshCarriersTask::class)]
class RefreshCarriersTaskTest extends TestCase
{
    public function testTheAccountsCarriersAreRefreshed(): void
    {
        $carriers = $this->createMock(CarrierConfigSynchronizer::class);
        $carriers->expects(self::once())->method('synchronize')->willReturn(12);

        (new RefreshCarriersTask(
            $carriers,
            $this->createConfig(true, true),
            $this->createStub(Logger::class),
        ))->execute();
    }

    /**
     * @return array<string, array{bool, bool}>
     */
    public static function provideNotToRun(): array
    {
        return [
            'synchronization off' => [false, true],
            'no credentials' => [true, false],
        ];
    }

    #[DataProvider('provideNotToRun')]
    public function testNothingIsAskedOfPolkurierWhenItShouldntBe(bool $enabled, bool $credentials): void
    {
        $carriers = $this->createMock(CarrierConfigSynchronizer::class);
        $carriers->expects(self::never())->method('synchronize');

        (new RefreshCarriersTask(
            $carriers,
            $this->createConfig($enabled, $credentials),
            $this->createStub(Logger::class),
        ))->execute();
    }

    public function testAFailureIsLoggedForTheNextRunToTryAgain(): void
    {
        $failure = new \RuntimeException('Timed out');
        $carriers = $this->createStub(CarrierConfigSynchronizer::class);
        $carriers->method('synchronize')->willThrowException($failure);
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('Polkurier carrier list refresh failed', ['exception' => $failure]);

        (new RefreshCarriersTask($carriers, $this->createConfig(true, true), $logger))->execute();
    }

    private function createConfig(bool $enabled, bool $credentials): ConfigProvider
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isSyncEnabled')->willReturn($enabled);
        $config->method('hasCredentials')->willReturn($credentials);

        return $config;
    }
}
