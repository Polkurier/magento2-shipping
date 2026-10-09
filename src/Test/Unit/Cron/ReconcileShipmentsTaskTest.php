<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Cron;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Cron\ReconcileShipmentsTask;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\AttentionNotifier;
use Polkurier\Shipping\Model\Shipment\Placement\FailedShipmentManager;
use Polkurier\Shipping\Model\Shipment\Status\ReconciliationService;

#[CoversClass(ReconcileShipmentsTask::class)]
class ReconcileShipmentsTaskTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $ran = [];

    public function testExpiredShipmentsGoThenUnansweredOnesAreLookedForThenTheAdminIsTold(): void
    {
        $this->task(true)->execute();

        self::assertSame(['expire', 'reconcile', 'notify'], $this->ran);
    }

    public function testWithoutCredentialsOnlyWhatAsksNothingOfPolkurierRuns(): void
    {
        $this->task(false)->execute();

        self::assertSame(['expire', 'notify'], $this->ran);
    }

    public function testTheAdminIsToldEvenWhenTheLookingFails(): void
    {
        try {
            $this->task(true, new \RuntimeException('Polkurier is not answering.'))->execute();
            self::fail('The failure goes on to the cron log.');
        } catch (\RuntimeException) {
        }

        self::assertSame(['expire', 'reconcile', 'notify'], $this->ran);
    }

    private function task(bool $credentials, ?\Throwable $failure = null): ReconcileShipmentsTask
    {
        $reconciler = $this->createStub(ReconciliationService::class);
        $reconciler->method('reconcilePending')->willReturnCallback(function () use ($failure): void {
            $this->ran[] = 'reconcile';
            if ($failure !== null) {
                throw $failure;
            }
        });
        $config = $this->createStub(ConfigProvider::class);
        $config->method('hasCredentials')->willReturn($credentials);
        $failedShipmentManager = $this->createStub(FailedShipmentManager::class);
        $failedShipmentManager->method('expire')->willReturnCallback(function (): int {
            $this->ran[] = 'expire';

            return 0;
        });
        $attentionNotifier = $this->createStub(AttentionNotifier::class);
        $attentionNotifier->method('notify')->willReturnCallback(function (): void {
            $this->ran[] = 'notify';
        });

        return new ReconcileShipmentsTask($reconciler, $config, $failedShipmentManager, $attentionNotifier);
    }
}
