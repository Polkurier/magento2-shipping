<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Status;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
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
use Polkurier\Webservice\Response\Order as RemoteOrder;

#[CoversClass(StatusRefreshService::class)]
#[AllowMockObjectsWithoutExpectations]
class StatusRefreshServiceTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $happened = [];

    private Shipment $shipment;

    /**
     * @var array{0: string[], 1: ?string, 2: ?string}|null the waybills, check time and error of
     *     the shipment as last saved
     */
    private ?array $saved = null;

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

        self::assertSame(
            ['save', 'remark 11: Polkurier order 711400-27 delivered: 2026-10-06 09:00:00.'],
            $this->happened,
        );

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

    public function testNewWaybillsGoOnTheMagentoShipmentAndThenTheCustomerMayBeEmailed(): void
    {
        $this->refresh(11, ['waybills' => ['W1', 'W2']])->refresh(5);

        self::assertSame(
            [
                'save',
                'tracks 11: W1, W2',
                'comment: Waybills issued for Polkurier order 711400-27: W1, W2.',
                'notify 11',
            ],
            $this->happened,
        );
    }

    public function testAReturnOrAnExceptionIsNotedOnTheOrderOnce(): void
    {
        $this->refresh(11, ['status' => ['Z', 'Zwrot do nadawcy']])->refresh(5);

        self::assertSame(
            ['save', 'comment: Polkurier order 711400-27 status: Zwrot do nadawcy.'],
            $this->happened,
        );

        $this->happened = [];
        $this->refresh(11, ['status' => ['Z', 'Zwrot do nadawcy']], statusBefore: 'Z')->refresh(5);

        self::assertSame(['save'], $this->happened, 'Still being returned: noted already.');

        $this->happened = [];
        $this->refresh(11, ['status' => ['WP', 'W drodze']])->refresh(5);

        self::assertSame(['save'], $this->happened, 'Other statuses are not noted.');
    }

    public function testWithoutNewWaybillsTheCustomerIsNotEmailedHere(): void
    {
        $this->refresh(11, ['waybills' => ['W1']])->refresh(5);

        self::assertSame(['save'], $this->happened);
    }

    public function testWhatPolkurierSaidIsKeptWhenAddingTheWaybillsFailsAfterwards(): void
    {
        $refresher = $this->refresh(
            11,
            ['waybills' => ['W1', 'W2']],
            tracksError: new LocalizedException(__('The shipment is locked.')),
        );
        try {
            $refresher->refresh(5);
            self::fail('The failure is not swallowed.');
        } catch (LocalizedException $e) {
            self::assertSame('The shipment is locked.', $e->getMessage());
        }

        self::assertSame(['save'], $this->happened, 'Saved before the Magento shipment was touched.');
        self::assertSame(
            [['W1', 'W2'], '2026-10-06 10:00:00', null],
            $this->saved,
            'The new waybills and the check time are stored, so the next run does not apply them again.',
        );
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

    public function testOrdersListedTogetherAreAppliedAsEachWouldBeAndTheRestReadByNumber(): void
    {
        $results = $this->many(listed: ['711400-6', '711400-8'])->refreshMany([6, 7, 8]);

        self::assertSame([
            'list 711400-6, 711400-7, 711400-8',
            'apply 711400-6 to 6',
            'find 711400-7',
            'apply 711400-7 to 7',
            'apply 711400-8 to 8',
        ], $this->happened);
        self::assertSame([6, 7, 8], \array_keys($results));
        foreach ($results as $id => $shipment) {
            self::assertInstanceOf(ShipmentInterface::class, $shipment);
            self::assertSame('2026-10-06 10:00:00', $shipment->getStatusCheckedAt(), 'Shipment ' . $id);
        }
    }

    public function testAShipmentThatFailsDoesNotStopTheOthers(): void
    {
        $results = $this->many(listed: ['711400-9'], unknown: ['711400-6'], failing: ['711400-7'])
            ->refreshMany([4, 5, 6, 7, 8, 9]);

        self::assertSame([4, 5, 6, 7, 8, 9], \array_keys($results), 'In the order given.');
        self::assertSame(
            [
                4 => 'No such shipment.',
                5 => 'This shipment has no Polkurier order to read.',
                6 => 'Polkurier has no order number 711400-6 on this account.',
                7 => 'Unexpected.',
            ],
            \array_map(
                static fn(\Throwable $e): string => $e->getMessage(),
                \array_filter($results, static fn($result): bool => $result instanceof \Throwable),
            ),
        );
        self::assertInstanceOf(LocalizedException::class, $results[6]);
        self::assertNotInstanceOf(LocalizedException::class, $results[7], 'Passed on as it is.');
        self::assertInstanceOf(ShipmentInterface::class, $results[8]);
        self::assertInstanceOf(ShipmentInterface::class, $results[9]);
        self::assertSame('list 711400-6, 711400-7, 711400-8, 711400-9', $this->happened[0], 'Without those not read.');
    }

    /**
     * @param array{waybills?: string[], delivered?: string, state?: string} $remote what Polkurier
     *     says, as {@see RemoteOrderApplier} would apply it
     * @param \Throwable|null $tracksError what adding the waybills to the Magento shipment throws
     * @param string|null $statusBefore the status code the shipment had before
     */
    private function refresh(
        ?int $magentoShipmentId,
        array $remote,
        ?string $deliveredBefore = null,
        ?string $number = '711400-27',
        ?string $shipError = null,
        string $state = ShipmentInterface::STATE_CREATED,
        bool $old = true,
        ?\Throwable $tracksError = null,
        ?string $statusBefore = null,
    ): StatusRefreshService {
        $this->shipment = (new ObjectManager($this))->getObject(Shipment::class);
        $this->shipment->setId(5)->setOrderId(29)->setState($state)->setPolkurierNumber($number)
            ->setShipmentId($magentoShipmentId)->setWaybills(['W1'])->setDeliveredAt($deliveredBefore)
            ->setStatusCode($statusBefore)
            ->setLastError('The Magento shipment could not be created');
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('get')->willReturn($this->shipment);
        $repository->method('save')->willReturnCallback(function (ShipmentInterface $shipment): ShipmentInterface {
            $this->happened[] = 'save';
            $this->saved = [$shipment->getWaybills(), $shipment->getStatusCheckedAt(), $shipment->getLastError()];

            return $shipment;
        });
        $reconciler = $this->createStub(RemoteOrderMatcher::class);
        $reconciler->method('findByNumber')->willReturn($this->createStub(RemoteOrder::class));
        $snapshot = $this->createStub(RemoteOrderApplier::class);
        $snapshot->method('apply')->willReturnCallback(
            static function (RemoteOrder $order, ShipmentInterface $shipment) use ($remote): void {
                $shipment->setWaybills($remote['waybills'] ?? $shipment->getWaybills());
                if (isset($remote['delivered'])) {
                    $shipment->setDeliveredAt($remote['delivered']);
                }
                if (isset($remote['state'])) {
                    $shipment->setState($remote['state']);
                }
                if (isset($remote['status'])) {
                    $shipment->setStatusCode($remote['status'][0])->setStatusLabel($remote['status'][1]);
                }
            },
        );
        $magentoShipment = $this->createStub(NativeShipmentService::class);
        $magentoShipment->method('addTracks')->willReturnCallback(
            function (Order $order, int $id, ShipmentInterface $shipment, array $waybills) use ($tracksError): void {
                if ($tracksError !== null) {
                    throw $tracksError;
                }
                $this->happened[] = 'tracks ' . $id . ': ' . \implode(', ', $waybills);
            },
        );
        $followUp = $this->createStub(NativeFollowUpService::class);
        $orderLoader = $this->createStub(OrderLoader::class);
        $orderLoader->method('get')->willReturn($this->createStub(Order::class));
        $followUp->method('waybillList')->willReturnCallback(
            static fn(ShipmentInterface $shipment): string => \implode(', ', $shipment->getWaybills()),
        );
        $followUp->method('comment')->willReturnCallback(function (Order $order, Phrase $comment): void {
            $this->happened[] = 'comment: ' . $comment;
        });
        $followUp->method('remark')->willReturnCallback(function (?int $id, Phrase $remark): void {
            $this->happened[] = 'remark ' . $id . ': ' . $remark;
        });
        $followUp->method('cancelled')->willReturnCallback(
            function (ShipmentInterface $shipment, bool $inPolkurier): Order {
                $this->happened[] = $inPolkurier ? 'cancelled in Polkurier' : 'cancelled here';

                return $this->createStub(Order::class);
            },
        );
        $followUp->method('notifyCustomer')->willReturnCallback(
            function (Order $order, ShipmentInterface $shipment): void {
                $this->happened[] = 'notify ' . $shipment->getShipmentId();
            },
        );
        $followUp->method('ship')->willReturnCallback(function () use ($shipError): ?string {
            $this->happened[] = 'ship';

            return $shipError;
        });
        $format = $this->createStub(Formatter::class);
        $format->method('storedTime')->willReturnArgument(0);

        $age = $this->createStub(ShipmentAgeChecker::class);
        $age->method('isOlderThan')->willReturn($old);

        return new StatusRefreshService(
            $repository,
            $reconciler,
            $snapshot,
            $magentoShipment,
            $followUp,
            $orderLoader,
            new FixedDateTime((int)\strtotime('2026-10-06 10:00:00 UTC')),
            $format,
            new OrderLock(new MemoryLocks()),
            $age,
            $this->createStub(ShipmentFinder::class),
            $this->createStub(ShipmentResource::class),
            $this->createStub(ConfigProvider::class),
            $this->createStub(Logger::class),
        );
    }

    /**
     * Shipments 6 and up placed as Polkurier order 711400-<id>; 4 doesn't exist and 5 has no
     * Polkurier order.
     *
     * @param string[] $listed the orders Polkurier lists together
     * @param string[] $unknown the orders Polkurier doesn't find by their numbers
     * @param string[] $failing the orders that fail unexpectedly once applied
     */
    private function many(array $listed, array $unknown = [], array $failing = []): StatusRefreshService
    {
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('get')->willReturnCallback(function (int $id): ShipmentInterface {
            if ($id === 4) {
                throw new NoSuchEntityException(__('No such shipment.'));
            }
            /** @var Shipment $shipment */
            $shipment = (new ObjectManager($this))->getObject(Shipment::class);

            return $shipment->setId($id)->setOrderId(29)->setState(ShipmentInterface::STATE_CREATED)
                ->setPolkurierNumber($id === 5 ? null : '711400-' . $id)->setShipmentId(11)->setWaybills(['W1']);
        });
        $repository->method('save')->willReturnArgument(0);
        $remote = function (string $number): RemoteOrder {
            $order = $this->createStub(RemoteOrder::class);
            $order->method('getNumber')->willReturn($number);

            return $order;
        };
        $matcher = $this->createStub(RemoteOrderMatcher::class);
        $matcher->method('findByNumbers')->willReturnCallback(
            function (array $shipments) use ($listed, $remote): array {
                $this->happened[] = 'list ' . \implode(', ', \array_map(
                    static fn(ShipmentInterface $shipment): string => (string)$shipment->getPolkurierNumber(),
                    $shipments,
                ));

                return \array_combine($listed, \array_map($remote, $listed));
            },
        );
        $matcher->method('findByNumber')->willReturnCallback(
            function (string $number) use ($unknown, $remote): RemoteOrder {
                $this->happened[] = 'find ' . $number;
                if (\in_array($number, $unknown, true)) {
                    throw new LocalizedException(__('Polkurier has no order number %1 on this account.', $number));
                }

                return $remote($number);
            },
        );
        $applier = $this->createStub(RemoteOrderApplier::class);
        $applier->method('apply')->willReturnCallback(
            function (RemoteOrder $order, ShipmentInterface $shipment) use ($failing): void {
                if (\in_array($order->getNumber(), $failing, true)) {
                    throw new \LogicException('Unexpected.');
                }
                $this->happened[] = 'apply ' . $order->getNumber() . ' to ' . $shipment->getId();
            },
        );

        return new StatusRefreshService(
            $repository,
            $matcher,
            $applier,
            $this->createStub(NativeShipmentService::class),
            $this->createStub(NativeFollowUpService::class),
            $this->createStub(OrderLoader::class),
            new FixedDateTime((int)\strtotime('2026-10-06 10:00:00 UTC')),
            $this->createStub(Formatter::class),
            new OrderLock(new MemoryLocks()),
            $this->createStub(ShipmentAgeChecker::class),
            $this->createStub(ShipmentFinder::class),
            $this->createStub(ShipmentResource::class),
            $this->createStub(ConfigProvider::class),
            $this->createStub(Logger::class),
        );
    }
}
