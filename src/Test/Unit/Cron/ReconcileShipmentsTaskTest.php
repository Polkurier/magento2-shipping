<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Cron;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Cron\ReconcileShipmentsTask;
use Polkurier\Shipping\Model\Admin\AttentionNotifier;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Shipment\AutoReconciler;
use Polkurier\Shipping\Model\Shipment\NotPlaced;

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
        $reconciler = $this->createStub(AutoReconciler::class);
        $reconciler->method('run')->willReturnCallback(function () use ($failure): int {
            $this->ran[] = 'reconcile';
            if ($failure !== null) {
                throw $failure;
            }

            return 0;
        });
        $config = $this->createStub(Config::class);
        $config->method('hasCredentials')->willReturn($credentials);
        $notPlaced = $this->createStub(NotPlaced::class);
        $notPlaced->method('expire')->willReturnCallback(function (): int {
            $this->ran[] = 'expire';

            return 0;
        });
        $attention = $this->createStub(AttentionNotifier::class);
        $attention->method('notify')->willReturnCallback(function (): int {
            $this->ran[] = 'notify';

            return 0;
        });

        return new ReconcileShipmentsTask($reconciler, $config, $notPlaced, $attention);
    }
}
