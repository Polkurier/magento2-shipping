<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNames;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\MagentoFollowUp;
use Polkurier\Shipping\Model\Shipment\NotPlaced;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;

#[CoversClass(NotPlaced::class)]
#[AllowMockObjectsWithoutExpectations]
class NotPlacedTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $log = [];

    /**
     * @var array<string, mixed>
     */
    private array $filters = [];

    public function testOneLastTriedTwoDaysAgoIsRemovedAndNotedOnItsOrder(): void
    {
        $shipment = $this->shipment(ShipmentInterface::STATE_FAILED, 'Przerwa techniczna.');

        self::assertSame(1, $this->notPlaced([$shipment])->expire());
        self::assertSame(ShipmentInterface::STATE_FAILED, $this->filters[ShipmentInterface::STATE]);
        self::assertSame(['lt' => '2026-09-30 10:00:00'], $this->filters[ShipmentInterface::CREATED_AT]);
        self::assertSame([
            'delete 7',
            'comment The DPD shipment that wasn\'t placed was removed, 48 hours after it was last tried. Why it wasn\'t placed: Przerwa techniczna',
        ], $this->log);
    }

    public function testItIsRemovedTwoDaysAfterItsLastAttempt(): void
    {
        self::assertSame('2026-10-03 08:30:00', $this->notPlaced([])->expiresAt($this->shipment(ShipmentInterface::STATE_FAILED, '')));
    }

    public function testDiscardingRemovesIt(): void
    {
        $shipment = $this->shipment(ShipmentInterface::STATE_FAILED, '');

        $this->notPlaced([], $shipment)->discard(7);

        self::assertSame(['delete 7', 'comment The DPD shipment that wasn\'t placed was discarded.'], $this->log);
    }

    public function testAPlacedOneCannotBeDiscarded(): void
    {
        $this->expectException(LocalizedException::class);

        $this->notPlaced([], $this->shipment(ShipmentInterface::STATE_CREATED, ''))->discard(7);
    }

    /**
     * @param Shipment[] $due
     */
    private function notPlaced(array $due, ?Shipment $one = null): NotPlaced
    {
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('get')->willReturnCallback(fn(): ShipmentInterface => $one ?? throw new \LogicException());
        $repository->method('delete')->willReturnCallback(function (ShipmentInterface $shipment): bool {
            $this->log[] = 'delete ' . $shipment->getId();

            return true;
        });
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(function (string $field, mixed $condition) use ($collection): Collection {
            $this->filters[$field] = $condition;

            return $collection;
        });
        $collection->method('getItems')->willReturn($due);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $followUp = $this->createStub(MagentoFollowUp::class);
        $followUp->method('order')->willReturn($this->createStub(Order::class));
        $followUp->method('comment')->willReturnCallback(function (Order $order, Phrase $comment): void {
            $this->log[] = 'comment ' . $comment;
        });
        $names = $this->createStub(CarrierNames::class);
        $names->method('get')->willReturn('DPD');

        return new NotPlaced($repository, $factory, $followUp, $names, new FixedDateTime((int)\strtotime('2026-10-02 10:00:00 UTC')));
    }

    private function shipment(string $state, string $error): Shipment
    {
        $shipment = $this->getMockBuilder(Shipment::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $shipment->setData(['state' => $state, 'order_id' => 42, 'carrier_code' => 'DPD', 'last_error' => $error, 'created_at' => '2026-10-01 08:30:00'])->setId(7);

        return $shipment;
    }
}
