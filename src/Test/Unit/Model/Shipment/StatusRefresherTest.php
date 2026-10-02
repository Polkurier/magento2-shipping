<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\StatusRefresher;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Psr\Log\NullLogger;

#[CoversClass(StatusRefresher::class)]
#[AllowMockObjectsWithoutExpectations]
class StatusRefresherTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $filters = [];
    private ?string $order = null;

    /**
     * @var array<int, array<string, ?string>> rows as the database has them, by id
     */
    private array $stored = [];
    private ?\Throwable $failure = null;
    private int $interval = 30;

    public function testOnlyUnfinishedShipmentsAreTakenLeastRecentlyCheckedFirst(): void
    {
        $this->refresher([])->refreshPending();

        self::assertSame(ShipmentInterface::STATE_CREATED, $this->filters[ShipmentInterface::STATE]);
        self::assertSame(['eq' => 0], $this->filters[ShipmentInterface::IS_FINAL], 'Finished shipments are excluded by the query.');
        self::assertArrayNotHasKey(ShipmentInterface::STORE_ID, $this->filters, 'One account: every store\'s shipments.');
        self::assertSame([['null' => true], ['lt' => '2026-09-30 09:31:00']], $this->filters[ShipmentInterface::STATUS_CHECKED_AT], 'Those the previous half-hourly run checked are due again.');
        self::assertSame(ShipmentInterface::STATUS_CHECKED_AT, $this->order);
    }

    public function testTheScheduleSetsHowRecentlyCheckedIsTooRecent(): void
    {
        $this->interval = 10;
        $this->refresher([])->refreshPending();

        self::assertSame([['null' => true], ['lt' => '2026-09-30 09:51:00']], $this->filters[ShipmentInterface::STATUS_CHECKED_AT]);
    }

    public function testUpdatingNowTakesEveryUnfinishedShipmentHoweverRecentlyChecked(): void
    {
        $result = $this->refresher([$this->shipment(5), $this->shipment(7)], false)->refreshNow();

        self::assertSame(['refreshed' => 1, 'failed' => 1], $result, 'Also with scheduled updates off.');
        self::assertArrayNotHasKey(ShipmentInterface::STATUS_CHECKED_AT, $this->filters);
        self::assertSame(ShipmentInterface::STATE_CREATED, $this->filters[ShipmentInterface::STATE]);
    }

    public function testNothingIsReadWithStatusUpdatesOff(): void
    {
        self::assertSame(0, $this->refresher([], false)->refreshPending());
        self::assertSame([], $this->filters);
    }

    public function testAShipmentWhoseRefreshFailsIsStillMarkedAsChecked(): void
    {
        $checked = [];

        $refreshed = $this->refresher([$this->shipment(7), $this->shipment(8)], true, $checked)->refreshPending();

        self::assertSame(1, $refreshed);
        self::assertSame([7 => '2026-09-30 10:00:00'], $checked, 'It must not stay first in line.');
    }

    public function testWhatAFailingRefreshSavedIsKept(): void
    {
        // The refresh saves the new status, then fails on the Magento side.
        $this->stored = [7 => ['status_code' => 'D', 'status_checked_at' => '2026-09-29 10:00:00']];
        $checked = [];

        $this->refresher([$this->shipment(7)->setStatusCode('P')], true, $checked)->refreshPending();

        self::assertSame('D', $this->stored[7]['status_code'], 'The stale row loaded for the batch is not written back.');
        self::assertNotSame('2026-09-29 10:00:00', $this->stored[7]['status_checked_at']);
    }

    public function testAnErrorOfAnyKindDoesNotStopTheBatch(): void
    {
        $this->failure = new \LogicException('Unexpected.');
        $checked = [];

        $refreshed = $this->refresher([$this->shipment(7), $this->shipment(8)], true, $checked)->refreshPending();

        self::assertSame(1, $refreshed);
        self::assertSame([7], \array_keys($checked));
    }

    /**
     * @param Shipment[] $shipments
     * @param array<int, string> $checked check times the refresher recorded, by shipment id
     */
    private function refresher(array $shipments, bool $statusSync = true, array &$checked = []): StatusRefresher
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(function (string $field, mixed $condition) use ($collection): Collection {
            $this->filters[$field] = $condition;

            return $collection;
        });
        $collection->method('setOrder')->willReturnCallback(function (string $field) use ($collection): Collection {
            $this->order = $field;

            return $collection;
        });
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getItems')->willReturn($shipments);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('refreshStatus')->willReturnCallback(function (int $id): ShipmentInterface {
            if ($id === 7) {
                if (isset($this->stored[7])) {
                    $this->stored[7]['status_checked_at'] = '2026-09-29 12:00:00';
                }
                throw $this->failure ?? new LocalizedException(\__('Polkurier is not answering.'));
            }

            return $this->shipment($id);
        });
        $resource = $this->createStub(ShipmentResource::class);
        $resource->method('markChecked')->willReturnCallback(function (int $id, string $at) use (&$checked): void {
            $checked[$id] = $at;
            if (isset($this->stored[$id])) {
                $this->stored[$id]['status_checked_at'] = $at;
            }
        });

        $config = $this->createStub(Config::class);
        $config->method('isStatusSyncEnabled')->willReturn($statusSync);
        $config->method('getStatusIntervalMinutes')->willReturnCallback(fn(): int => $this->interval);

        return new StatusRefresher($factory, $management, $resource, $config, new NullLogger(), new FixedDateTime((int)\strtotime('2026-09-30 10:00:00 UTC')));
    }

    private function shipment(int $id): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);

        return $shipment->setId($id)->setState(ShipmentInterface::STATE_CREATED);
    }
}
