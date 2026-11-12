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
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\OrderLock;
use Polkurier\Shipping\Model\Shipment\ShipmentAgeChecker;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\Shipment\Status\ReconciliationService;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderApplier;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderMatcher;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Shipping\Test\Unit\Support\MemoryLocks;

#[CoversClass(ReconciliationService::class)]
#[AllowMockObjectsWithoutExpectations]
class ReconciliationServiceBatchTest extends TestCase
{
    /**
     * @var array{0: string, 1: string, 2: int}|null what the due shipments were looked up by:
     *     attempted before, not checked since, and how many
     */
    private ?array $query = null;

    /**
     * @var array<int, array<string, ?string>> rows as the database has them, by id
     */
    private array $stored = [];

    private ?\Throwable $failure = null;

    public function testTakesUnconfirmedAndInterruptedShipments(): void
    {
        $checked = [];
        $this->createService(
            [$this->createShipment(1), $this->createShipment(2), $this->createShipment(3)],
            [1 => ShipmentInterface::STATE_CREATED, 2 => ShipmentInterface::STATE_UNKNOWN],
            $checked,
        )->reconcilePending();

        self::assertSame(
            '2026-09-30 09:55:00',
            $this->query[0] ?? null,
            'A fresh pending one may still get its answer.',
        );
        self::assertSame(
            '2026-09-30 09:55:00',
            $this->query[1] ?? null,
            'One checked in the last five minutes waits its turn.',
        );
        self::assertSame(ReconciliationService::BATCH_SIZE, $this->query[2] ?? null);
        self::assertSame(
            [3 => '2026-09-30 10:00:00'],
            $checked,
            'The one that failed is marked as checked, so it does not stay first in line.',
        );
    }

    public function testNothingDueSettlesNothing(): void
    {
        $checked = [];

        $this->createService([], [], $checked)->reconcilePending();

        self::assertSame([], $checked);
    }

    public function testWhatAFailingReconciliationSavedIsKept(): void
    {
        // The reconciliation adopts the order and saves it, then fails on the Magento side.
        $this->stored = [
            3 => ['state' => ShipmentInterface::STATE_UNKNOWN, 'status_checked_at' => '2026-09-29 10:00:00'],
        ];
        $checked = [];

        $this->createService([$this->createShipment(3)], [], $checked)->reconcilePending();

        self::assertSame(
            ShipmentInterface::STATE_CREATED,
            $this->stored[3]['state'],
            'The stale row loaded for the batch is not written back.',
        );
        self::assertSame('2026-09-30 10:00:00', $this->stored[3]['status_checked_at']);
    }

    public function testAnErrorOfAnyKindDoesNotStopTheBatch(): void
    {
        $this->failure = new \LogicException('Unexpected.');
        $checked = [];

        $this->createService(
            [$this->createShipment(1), $this->createShipment(2)],
            [2 => ShipmentInterface::STATE_CREATED],
            $checked,
        )->reconcilePending();

        self::assertSame([1], \array_keys($checked));
    }

    /**
     * @param Shipment[] $shipments
     * @param array<int, string> $outcomes state after reconciling, by id; missing ones fail
     * @param array<int, string> $checked check times the reconciler recorded, by shipment id
     */
    private function createService(array $shipments, array $outcomes, array &$checked): ReconciliationService
    {
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('findDueForReconciliation')->willReturnCallback(
            function (string $attemptedBefore, string $checkedBefore, int $limit) use ($shipments): array {
                $this->query = [$attemptedBefore, $checkedBefore, $limit];

                return $shipments;
            },
        );

        $reconcile = function (int $id) use ($outcomes): ShipmentInterface {
            if (!isset($outcomes[$id])) {
                if (isset($this->stored[$id])) {
                    $this->stored[$id] = [
                        'state' => ShipmentInterface::STATE_CREATED,
                        'status_checked_at' => '2026-09-30 09:59:00',
                    ];
                }
                throw $this->failure ?? new LocalizedException(__('Polkurier is not answering.'));
            }

            return $this->createShipment($id)->setState($outcomes[$id]);
        };
        // The whole row written back, whichever way it is saved.
        $writeBack = function (Shipment $shipment): void {
            $this->stored[(int)$shipment->getId()] = [
                'state' => $shipment->getState(),
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

        $clock = new FixedDateTime((int)\strtotime('2026-09-30 10:00:00 UTC'));
        $reconciliation = $this->getMockBuilder(ReconciliationService::class)->setConstructorArgs([
            $repository,
            $this->createStub(RemoteOrderMatcher::class),
            $this->createStub(RemoteOrderApplier::class),
            new ShipmentAgeChecker($clock),
            $this->createStub(NativeFollowUpService::class),
            $this->createStub(OrderLoader::class),
            $clock,
            new OrderLock(new MemoryLocks()),
            $finder,
            $resource,
            $this->createStub(Logger::class),
        ])->onlyMethods(['reconcile'])->getMock();
        // Each shipment's own reconciliation is
        // {@see \Polkurier\Shipping\Test\Unit\Model\Shipment\Status\ReconciliationServiceTest}'s.
        $reconciliation->method('reconcile')->willReturnCallback($reconcile);

        return $reconciliation;
    }

    private function createShipment(int $id): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);

        return $shipment->setId($id)->setState(ShipmentInterface::STATE_UNKNOWN);
    }
}
