<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Cron;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Cron\RefreshShipmentStatusTask;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;

#[CoversClass(RefreshShipmentStatusTask::class)]
class RefreshShipmentStatusTaskTest extends TestCase
{
    public function testTheDueShipmentsAreRefreshed(): void
    {
        $refresher = $this->createMock(StatusRefreshService::class);
        $refresher->expects(self::once())->method('refreshPending');

        (new RefreshShipmentStatusTask($refresher, $this->config(true, true)))->execute();
    }

    /**
     * @return array<string, array{bool, bool}>
     */
    public static function notToRun(): array
    {
        return [
            'scheduled updates off' => [false, true],
            'no credentials' => [true, false],
        ];
    }

    #[DataProvider('notToRun')]
    public function testNothingIsAskedOfPolkurierWhenItShouldntBe(bool $enabled, bool $credentials): void
    {
        $refresher = $this->createMock(StatusRefreshService::class);
        $refresher->expects(self::never())->method('refreshPending');

        (new RefreshShipmentStatusTask($refresher, $this->config($enabled, $credentials)))->execute();
    }

    private function config(bool $enabled, bool $credentials): ConfigProvider
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isStatusSyncEnabled')->willReturn($enabled);
        $config->method('hasCredentials')->willReturn($credentials);

        return $config;
    }
}
