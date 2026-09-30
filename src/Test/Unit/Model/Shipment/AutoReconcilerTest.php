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
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\AutoReconciler;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Psr\Log\NullLogger;

#[CoversClass(AutoReconciler::class)]
#[AllowMockObjectsWithoutExpectations]
class AutoReconcilerTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $filters = [];
    private ?\Throwable $failure = null;

    public function testTakesUnconfirmedAndInterruptedShipmentsAndCountsTheSettledOnes(): void
    {
        $saved = [];
        $settled = $this->reconciler(
            [$this->shipment(1), $this->shipment(2), $this->shipment(3)],
            [1 => ShipmentInterface::STATE_CREATED, 2 => ShipmentInterface::STATE_UNKNOWN],
            $saved,
        )->run();

        self::assertSame(
            ['in' => [ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_PENDING]],
            $this->filters[ShipmentInterface::STATE],
        );
        self::assertSame(['lt' => '2026-09-30 09:55:00'], $this->filters[ShipmentInterface::CREATED_AT], 'A fresh pending one may still get its answer.');
        self::assertSame(
            [['null' => true], ['lt' => '2026-09-30 09:55:00']],
            $this->filters[ShipmentInterface::STATUS_CHECKED_AT],
            'One checked in the last five minutes waits its turn.',
        );
        self::assertSame(1, $settled, 'Adopted counts; still not listed does not.');
        self::assertSame([3], $saved, 'The one that failed is marked as checked, so it does not stay first in line.');
    }

    public function testAnErrorOfAnyKindDoesNotStopTheBatch(): void
    {
        $this->failure = new \LogicException('Unexpected.');
        $saved = [];

        $settled = $this->reconciler([$this->shipment(1), $this->shipment(2)], [2 => ShipmentInterface::STATE_CREATED], $saved)->run();

        self::assertSame(1, $settled);
        self::assertSame([1], $saved);
    }

    /**
     * @param Shipment[] $shipments
     * @param array<int, string> $outcomes state after reconciling, by id; missing ones fail
     * @param int[] $saved ids of the shipments marked as checked
     */
    private function reconciler(array $shipments, array $outcomes, array &$saved): AutoReconciler
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(function (string $field, mixed $condition) use ($collection): Collection {
            $this->filters[$field] = $condition;

            return $collection;
        });
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getItems')->willReturn($shipments);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('reconcile')->willReturnCallback(function (int $id) use ($outcomes): ShipmentInterface {
            if (!isset($outcomes[$id])) {
                throw $this->failure ?? new LocalizedException(\__('Polkurier is not answering.'));
            }

            return $this->shipment($id)->setState($outcomes[$id]);
        });
        $resource = $this->createStub(ShipmentResource::class);
        $resource->method('markChecked')->willReturnCallback(function (int $id) use (&$saved): void {
            $saved[] = $id;
        });

        return new AutoReconciler($factory, $management, $resource, new NullLogger(), new FixedDateTime((int)\strtotime('2026-09-30 10:00:00 UTC')));
    }

    private function shipment(int $id): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);

        return $shipment->setId($id)->setState(ShipmentInterface::STATE_UNKNOWN);
    }
}
