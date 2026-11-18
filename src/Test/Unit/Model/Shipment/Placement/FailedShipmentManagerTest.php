<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Placement;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Admin\Formatter;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Placement\FailedShipmentManager;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;

#[CoversClass(FailedShipmentManager::class)]
#[AllowMockObjectsWithoutExpectations]
class FailedShipmentManagerTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $log = [];

    /**
     * The time before which the expiry looked for shipments not placed.
     */
    private ?string $attemptedBefore = null;

    public function testOneLastTriedTwoDaysAgoIsRemovedAndNotedOnItsOrder(): void
    {
        $shipment = $this->createShipment(ShipmentInterface::STATE_FAILED, 'Przerwa techniczna.');

        self::assertSame(1, $this->createManager([$shipment])->expire());
        self::assertSame('2026-09-30 10:00:00', $this->attemptedBefore);
        self::assertSame([
            'delete 7',
            'comment Unplaced shipment removed 48 hours after the last attempt. Carrier: DPD. '
            . 'Reason: Przerwa techniczna',
        ], $this->log);
    }

    public function testItIsRemovedTwoDaysAfterItsLastAttempt(): void
    {
        self::assertSame(
            '2026-10-03 08:30:00',
            $this->createManager([])->calculateExpiry('2026-10-01 08:30:00'),
        );
    }

    public function testDiscardingRemovesIt(): void
    {
        $shipment = $this->createShipment(ShipmentInterface::STATE_FAILED, '');

        $this->createManager([], $shipment)->discard(7);

        self::assertSame(
            ['delete 7', 'comment Unplaced shipment discarded. Carrier: DPD.'],
            $this->log,
        );
    }

    public function testAPlacedOneCannotBeDiscarded(): void
    {
        $this->expectException(LocalizedException::class);

        $this->createManager([], $this->createShipment(ShipmentInterface::STATE_CREATED, ''))->discard(7);
    }

    public function testWhatThePagesSayAboutItIsInTheAdminsTime(): void
    {
        $described = $this->createManager([])
            ->describe($this->createShipment(ShipmentInterface::STATE_FAILED, 'Przerwa techniczna.'));

        self::assertSame([
            'tried' => 'at 2026-10-01 08:30:00',
            'reason' => 'Przerwa techniczna',
            'removed' => 'at 2026-10-03 08:30:00',
        ], $described);
    }

    /**
     * @param Shipment[] $due
     */
    private function createManager(array $due, ?Shipment $one = null): FailedShipmentManager
    {
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('get')->willReturnCallback(fn(): ShipmentInterface => $one ?? throw new \LogicException());
        $repository->method('delete')->willReturnCallback(function (ShipmentInterface $shipment): bool {
            $this->log[] = 'delete ' . $shipment->getId();

            return true;
        });
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('findFailedBefore')->willReturnCallback(function (string $attemptedBefore) use ($due): array {
            $this->attemptedBefore = $attemptedBefore;

            return $due;
        });
        $followUp = $this->createStub(NativeFollowUpService::class);
        $orderLoader = $this->createStub(OrderLoader::class);
        $orderLoader->method('get')->willReturn($this->createStub(Order::class));
        $followUp->method('addComment')->willReturnCallback(function (Order $order, Phrase $comment): void {
            $this->log[] = 'comment ' . $comment;
        });
        $names = $this->createStub(CarrierNameProvider::class);
        $names->method('get')->willReturn('DPD');
        $format = $this->createStub(Formatter::class);
        $format->method('formatStoredTime')->willReturnCallback(static fn(?string $utc): string => 'at ' . $utc);

        return new FailedShipmentManager(
            $repository,
            $finder,
            $followUp,
            $orderLoader,
            $names,
            new FixedDateTime((int)\strtotime('2026-10-02 10:00:00 UTC')),
            $format,
        );
    }

    private function createShipment(string $state, string $error): Shipment
    {
        $shipment = (new ObjectManager($this))->getObject(Shipment::class, ['json' => new Json()]);
        $shipment
            ->setData([
                'state' => $state,
                'order_id' => 42,
                'carrier_code' => 'DPD',
                'last_error' => $error,
                'created_at' => '2026-10-01 08:30:00',
            ])
            ->setId(7);

        return $shipment;
    }
}
