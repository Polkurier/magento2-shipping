<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Status;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Details\Formatter;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\OrderLock;
use Polkurier\Shipping\Model\Shipment\ShipmentAgeChecker;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderApplier;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderMatcher;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Shipping\Test\Unit\Support\MemoryLocks;

#[CoversClass(StatusRefreshService::class)]
#[AllowMockObjectsWithoutExpectations]
class StatusRefreshServiceBatchTest extends TestCase
{
    /**
     * @var array{0: ?string, 1: int}|null what the due shipments were looked up by: not checked
     *     since, and how many
     */
    private ?array $query = null;

    /**
     * @var array<int, array<string, ?string>> rows as the database has them, by id
     */
    private array $stored = [];

    /**
     * @var array<int, int[]> the shipments refreshed, by call
     */
    private array $refreshedTogether = [];

    private ?\Throwable $failure = null;
    private int $interval = 30;

    public function testOnlyUnfinishedShipmentsAreTakenLeastRecentlyCheckedFirst(): void
    {
        $this->createService([])->refreshPending();

        self::assertSame(
            ['2026-09-30 09:31:00', StatusRefreshService::BATCH_SIZE],
            $this->query,
            'Those the previous half-hourly run checked are due again.',
        );
    }

    public function testTheScheduleSetsHowRecentlyCheckedIsTooRecent(): void
    {
        $this->interval = 10;
        $this->createService([])->refreshPending();

        self::assertSame('2026-09-30 09:51:00', $this->query[0] ?? null);
    }

    public function testUpdatingNowTakesEveryUnfinishedShipmentHoweverRecentlyChecked(): void
    {
        $result = $this->createService([$this->createShipment(5), $this->createShipment(7)])->refreshNow();

        self::assertSame(['refreshed' => 1, 'failed' => 1], $result, 'However recently checked.');
        self::assertSame([null, StatusRefreshService::BATCH_SIZE], $this->query);
        self::assertSame([[5, 7]], $this->refreshedTogether);
    }

    public function testAShipmentWhoseRefreshFailsIsStillMarkedAsChecked(): void
    {
        $checked = [];

        $this->createService([$this->createShipment(7), $this->createShipment(8)], $checked)->refreshPending();

        self::assertSame([7 => '2026-09-30 10:00:00'], $checked, 'It must not stay first in line.');
    }

    public function testWhatAFailingRefreshSavedIsKept(): void
    {
        // The refresh saves the new status, then fails on the Magento side.
        $this->stored = [7 => ['status_code' => 'D', 'status_checked_at' => '2026-09-29 10:00:00']];
        $checked = [];

        $this->createService([$this->createShipment(7)->setStatusCode('P')], $checked)->refreshPending();

        self::assertSame(
            'D',
            $this->stored[7]['status_code'],
            'The stale row loaded for the batch is not written back.',
        );
        self::assertSame(
            '2026-09-30 10:00:00',
            $this->stored[7]['status_checked_at'],
            'Only the check time is written.',
        );
    }

    public function testAnErrorOfAnyKindDoesNotStopTheBatch(): void
    {
        $this->failure = new \LogicException('Unexpected.');
        $checked = [];

        $this->createService([$this->createShipment(7), $this->createShipment(8)], $checked)->refreshPending();

        self::assertSame([7], \array_keys($checked));
    }

    /**
     * @param Shipment[] $shipments
     * @param array<int, string> $checked check times the refresher recorded, by shipment id
     */
    private function createService(array $shipments, array &$checked = []): StatusRefreshService
    {
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('findDueForStatusCheck')->willReturnCallback(
            function (?string $checkedBefore, int $limit) use ($shipments): array {
                $this->query = [$checkedBefore, $limit];

                return $shipments;
            },
        );

        $refresh = function (array $ids): array {
            $this->refreshedTogether[] = $ids;
            $results = [];
            foreach ($ids as $id) {
                if ($id === 7 && isset($this->stored[7])) {
                    $this->stored[7]['status_checked_at'] = '2026-09-29 12:00:00';
                }
                $results[$id] = $id === 7
                    ? $this->failure ?? new LocalizedException(__('Polkurier is not answering.'))
                    : $this->createShipment($id);
            }

            return $results;
        };
        // The whole row written back, whichever way it is saved.
        $writeBack = function (Shipment $shipment): void {
            $this->stored[(int)$shipment->getId()] = [
                'status_code' => $shipment->getStatusCode(),
                'status_checked_at' => $shipment->getStatusCheckedAt(),
            ];
        };
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(
            static function (Shipment $shipment) use ($writeBack): Shipment {
                $writeBack($shipment);

                return $shipment;
            },
        );
        $resource = $this->createStub(ShipmentResource::class);
        $resource->method('save')->willReturnCallback(
            static function (Shipment $shipment) use ($writeBack, $resource): ShipmentResource {
                $writeBack($shipment);

                return $resource;
            },
        );
        $resource->method('markChecked')->willReturnCallback(function (int $id, string $at) use (&$checked): void {
            $checked[$id] = $at;
            if (isset($this->stored[$id])) {
                $this->stored[$id]['status_checked_at'] = $at;
            }
        });

        $config = $this->createStub(ConfigProvider::class);
        $config->method('getStatusIntervalMinutes')->willReturnCallback(fn(): int => $this->interval);

        $clock = new FixedDateTime((int)\strtotime('2026-09-30 10:00:00 UTC'));
        $refresher = $this->getMockBuilder(StatusRefreshService::class)->setConstructorArgs([
            $repository,
            $this->createStub(RemoteOrderMatcher::class),
            $this->createStub(RemoteOrderApplier::class),
            $this->createStub(NativeShipmentService::class),
            $this->createStub(NativeFollowUpService::class),
            $this->createStub(OrderLoader::class),
            $clock,
            $this->createStub(Formatter::class),
            new OrderLock(new MemoryLocks()),
            new ShipmentAgeChecker($clock),
            $finder,
            $resource,
            $config,
            $this->createStub(Logger::class),
        ])->onlyMethods(['refreshMany'])->getMock();
        // Refreshing the shipments is
        // {@see \Polkurier\Shipping\Test\Unit\Model\Shipment\Status\StatusRefreshServiceTest}'s.
        $refresher->method('refreshMany')->willReturnCallback($refresh);

        return $refresher;
    }

    private function createShipment(int $id): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);

        return $shipment->setId($id)->setState(ShipmentInterface::STATE_CREATED);
    }
}
