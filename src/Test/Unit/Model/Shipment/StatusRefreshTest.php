<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Details\Formatter;
use Polkurier\Shipping\Model\Shipment\MagentoFollowUp;
use Polkurier\Shipping\Model\Shipment\MagentoShipment;
use Polkurier\Shipping\Model\Shipment\OrderLock;
use Polkurier\Shipping\Model\Shipment\OrderSnapshot;
use Polkurier\Shipping\Model\Shipment\Reconciler;
use Polkurier\Shipping\Model\Shipment\ShipmentAge;
use Polkurier\Shipping\Model\Shipment\StatusRefresh;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Shipping\Test\Unit\Support\MemoryLocks;
use Polkurier\Webservice\Response\Order as RemoteOrder;

#[CoversClass(StatusRefresh::class)]
#[AllowMockObjectsWithoutExpectations]
class StatusRefreshTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $happened = [];

    private Shipment $shipment;

    public function testTheShipmentTakesWhatPolkurierSaysAndWhenItWasChecked(): void
    {
        $shipment = $this->refresh(11, ['waybills' => ['W1']])->refresh(5);

        self::assertSame('2026-10-06 10:00:00', $shipment->getStatusCheckedAt());
        self::assertNull($shipment->getLastError());
        self::assertSame(['save'], $this->happened);
    }

    public function testADeliveryIsNotedOnTheMagentoShipmentOnce(): void
    {
        $this->refresh(11, ['delivered' => '2026-10-06 09:00:00'])->refresh(5);

        self::assertSame(['save', 'remark 11: Polkurier order 711400-27 delivered (2026-10-06 09:00:00).'], $this->happened);

        $this->happened = [];
        $this->refresh(11, ['delivered' => '2026-10-06 09:00:00'], deliveredBefore: '2026-10-06 09:00:00')->refresh(5);

        self::assertSame(['save'], $this->happened);
    }

    public function testACancellationInThePanelReleasesTheMagentoShipment(): void
    {
        $this->refresh(11, ['state' => ShipmentInterface::STATE_CANCELLED])->refresh(5);

        self::assertSame(['save', 'cancelled in Polkurier'], $this->happened);
    }

    public function testAPlacedOrderWithoutItsMagentoShipmentIsShippedAgain(): void
    {
        $this->refresh(null, [])->refresh(5);

        self::assertSame(['save', 'ship'], $this->happened);
    }

    public function testAPlacedOrderJustPlacedIsLeftForItsOwnRequestToShip(): void
    {
        // Magento saves a shipment made with a shipping label once the label is back.
        $this->refresh(null, [], old: false)->refresh(5);

        self::assertSame(['save'], $this->happened);
    }

    public function testNewWaybillsGoOnTheMagentoShipment(): void
    {
        $this->refresh(11, ['waybills' => ['W1', 'W2']])->refresh(5);

        self::assertSame(['save', 'tracks 11: W1, W2'], $this->happened);
    }

    public function testAShipmentWithoutAPolkurierNumberHasNothingToRead(): void
    {
        $this->expectExceptionMessage('This shipment has no Polkurier order to read.');

        $this->refresh(11, [], number: null)->refresh(5);
    }

    public function testShippingTheOrderClearsTheErrorItRecorded(): void
    {
        $shipment = $this->refresh(null, [])->shipOrder(5);

        self::assertNull($shipment->getLastError());
        self::assertSame(['ship', 'save'], $this->happened);
    }

    public function testShippingTheOrderSaysWhyItFailed(): void
    {
        try {
            $this->refresh(null, [], shipError: 'Not enough items')->shipOrder(5);
            self::fail('The failure is said.');
        } catch (LocalizedException $e) {
            self::assertSame('The Magento shipment could not be created: Not enough items', $e->getMessage());
        }
        self::assertSame(['ship'], $this->happened, 'Its error stays.');
    }

    public function testOnlyAPlacedOrderNotShippedYetCanBeShipped(): void
    {
        try {
            $this->refresh(11, [])->shipOrder(5);
            self::fail('Shipped already.');
        } catch (LocalizedException $e) {
            self::assertSame('The Magento order is already shipped with this Polkurier order.', $e->getMessage());
        }
        try {
            $this->refresh(null, [], state: ShipmentInterface::STATE_UNKNOWN)->shipOrder(5);
            self::fail('Not placed.');
        } catch (LocalizedException $e) {
            self::assertSame('Only a placed Polkurier order can be shipped in Magento.', $e->getMessage());
        }
        self::assertSame([], $this->happened);
    }

    /**
     * @param array{waybills?: string[], delivered?: string, state?: string} $remote what Polkurier
     *     says, as OrderSnapshot would apply it
     */
    private function refresh(
        ?int $magentoShipmentId,
        array $remote,
        ?string $deliveredBefore = null,
        ?string $number = '711400-27',
        ?string $shipError = null,
        string $state = ShipmentInterface::STATE_CREATED,
        bool $old = true,
    ): StatusRefresh {
        $this->shipment = (new ObjectManager($this))->getObject(Shipment::class);
        $this->shipment->setId(5)->setOrderId(29)->setState($state)->setPolkurierNumber($number)
            ->setShipmentId($magentoShipmentId)->setWaybills(['W1'])->setDeliveredAt($deliveredBefore)
            ->setLastError('The Magento shipment could not be created');
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('get')->willReturn($this->shipment);
        $repository->method('save')->willReturnCallback(function (ShipmentInterface $shipment): ShipmentInterface {
            $this->happened[] = 'save';

            return $shipment;
        });
        $reconciler = $this->createStub(Reconciler::class);
        $reconciler->method('findByNumber')->willReturn($this->createStub(RemoteOrder::class));
        $snapshot = $this->createStub(OrderSnapshot::class);
        $snapshot->method('apply')->willReturnCallback(static function (RemoteOrder $order, ShipmentInterface $shipment) use ($remote): void {
            $shipment->setWaybills($remote['waybills'] ?? $shipment->getWaybills());
            if (isset($remote['delivered'])) {
                $shipment->setDeliveredAt($remote['delivered']);
            }
            if (isset($remote['state'])) {
                $shipment->setState($remote['state']);
            }
        });
        $magentoShipment = $this->createStub(MagentoShipment::class);
        $magentoShipment->method('addTracks')->willReturnCallback(function (Order $order, int $id, ShipmentInterface $shipment, array $waybills): void {
            $this->happened[] = 'tracks ' . $id . ': ' . \implode(', ', $waybills);
        });
        $followUp = $this->createStub(MagentoFollowUp::class);
        $followUp->method('order')->willReturn($this->createStub(Order::class));
        $followUp->method('remark')->willReturnCallback(function (?int $id, Phrase $remark): void {
            $this->happened[] = 'remark ' . $id . ': ' . $remark;
        });
        $followUp->method('cancelled')->willReturnCallback(function (ShipmentInterface $shipment, bool $inPolkurier): Order {
            $this->happened[] = $inPolkurier ? 'cancelled in Polkurier' : 'cancelled here';

            return $this->createStub(Order::class);
        });
        $followUp->method('ship')->willReturnCallback(function () use ($shipError): ?string {
            $this->happened[] = 'ship';

            return $shipError;
        });
        $format = $this->createStub(Formatter::class);
        $format->method('storedTime')->willReturnArgument(0);

        $age = $this->createStub(ShipmentAge::class);
        $age->method('isOlderThan')->willReturn($old);

        return new StatusRefresh(
            $repository,
            $reconciler,
            $snapshot,
            $magentoShipment,
            $followUp,
            new FixedDateTime((int)\strtotime('2026-10-06 10:00:00 UTC')),
            $format,
            new OrderLock(new MemoryLocks()),
            $age,
        );
    }
}
