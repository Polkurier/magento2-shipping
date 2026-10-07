<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Placement;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Details\Formatter;
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
        $shipment = $this->shipment(ShipmentInterface::STATE_FAILED, 'Przerwa techniczna.');

        self::assertSame(1, $this->manager([$shipment])->expire());
        self::assertSame('2026-09-30 10:00:00', $this->attemptedBefore);
        self::assertSame([
            'delete 7',
            'comment The DPD shipment that wasn\'t placed was removed, 48 hours after it was last tried. '
            . 'Why it wasn\'t placed: Przerwa techniczna',
        ], $this->log);
    }

    public function testItIsRemovedTwoDaysAfterItsLastAttempt(): void
    {
        self::assertSame(
            '2026-10-03 08:30:00',
            $this->manager([])->expiresAt($this->shipment(ShipmentInterface::STATE_FAILED, '')),
        );
    }

    public function testDiscardingRemovesIt(): void
    {
        $shipment = $this->shipment(ShipmentInterface::STATE_FAILED, '');

        $this->manager([], $shipment)->discard(7);

        self::assertSame(
            ['delete 7', 'comment The DPD shipment that wasn\'t placed was discarded.'],
            $this->log,
        );
    }

    public function testAPlacedOneCannotBeDiscarded(): void
    {
        $this->expectException(LocalizedException::class);

        $this->manager([], $this->shipment(ShipmentInterface::STATE_CREATED, ''))->discard(7);
    }

    public function testWhatThePagesSayAboutItIsInTheAdminsTime(): void
    {
        $described = $this->manager([])
            ->describe($this->shipment(ShipmentInterface::STATE_FAILED, 'Przerwa techniczna.'));

        self::assertSame([
            'tried' => 'at 2026-10-01 08:30:00',
            'reason' => 'Przerwa techniczna',
            'removed' => 'at 2026-10-03 08:30:00',
        ], $described);
    }

    /**
     * @param Shipment[] $due
     */
    private function manager(array $due, ?Shipment $one = null): FailedShipmentManager
    {
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('get')->willReturnCallback(fn(): ShipmentInterface => $one ?? throw new \LogicException());
        $repository->method('delete')->willReturnCallback(function (ShipmentInterface $shipment): bool {
            $this->log[] = 'delete ' . $shipment->getId();

            return true;
        });
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('failedBefore')->willReturnCallback(function (string $attemptedBefore) use ($due): array {
            $this->attemptedBefore = $attemptedBefore;

            return $due;
        });
        $followUp = $this->createStub(NativeFollowUpService::class);
        $orderLoader = $this->createStub(OrderLoader::class);
        $orderLoader->method('get')->willReturn($this->createStub(Order::class));
        $followUp->method('comment')->willReturnCallback(function (Order $order, Phrase $comment): void {
            $this->log[] = 'comment ' . $comment;
        });
        $names = $this->createStub(CarrierNameProvider::class);
        $names->method('get')->willReturn('DPD');
        $format = $this->createStub(Formatter::class);
        $format->method('storedTime')->willReturnCallback(static fn(?string $utc): string => 'at ' . $utc);

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

    private function shipment(string $state, string $error): Shipment
    {
        $shipment = $this->getMockBuilder(Shipment::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
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
