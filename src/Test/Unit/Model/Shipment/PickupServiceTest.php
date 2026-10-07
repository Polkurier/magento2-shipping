<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Enum\ApiEventEnum;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\PickupService;
use Polkurier\Shipping\Model\Shipment\Placement\SenderMapper;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;
use Polkurier\Shipping\Test\Unit\Support\RecordingEventManager;
use Polkurier\Webservice\Builder\Courier\GetPickupTimeMessageBuilderFactory;
use Polkurier\Webservice\Builder\Order\MultiPickupAvailabilityRequestMessageBuilderFactory;
use Polkurier\Webservice\Builder\Order\MultiPickupRequestMessageBuilderFactory;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;
use Polkurier\Webservice\Request\Courier\GetPickupTimeMessage;
use Polkurier\Webservice\Request\Order\MultiPickupAvailabilityRequestMessage;
use Polkurier\Webservice\Request\Order\MultiPickupRequestMessage;
use Polkurier\Webservice\Request\Order\PackMessage;
use Polkurier\Webservice\Response\Courier\PickupTimeMessage;
use Polkurier\Webservice\Response\Courier\TimeIntervalMessage;
use Polkurier\Webservice\Response\Order\MultiPickupResultMessage;
use Psr\Log\NullLogger;

#[CoversClass(PickupService::class)]
#[AllowMockObjectsWithoutExpectations]
class PickupServiceTest extends TestCase
{
    private ?GetPickupTimeMessage $timesAsked = null;
    private ?MultiPickupRequestMessage $booking = null;
    private ?MultiPickupAvailabilityRequestMessage $availabilityAsked = null;
    private bool $multiPickupAvailable = true;

    /**
     * @var string[] the Polkurier orders the booking succeeds for
     */
    private array $bookable = [];

    /**
     * @var string[] the shipments saved, by Polkurier number
     */
    private array $saved = [];

    private RecordingEventManager $events;

    public function testCollectionTimesAreDaysWithTheirWindows(): void
    {
        $times = $this->pickups()->getPickupTimes('DPD', '05-620', '', 'box');

        self::assertSame('DPD', $this->timesAsked?->getCourier());
        self::assertSame('05-620', $this->timesAsked->getShipFrom());
        self::assertNull($this->timesAsked->getShipTo(), 'No recipient postcode is sent as none.');
        self::assertSame(ShipmentTypeEnum::Box, $this->timesAsked->getParcel());
        self::assertSame([
            [
                'date' => '2026-10-01',
                'windows' => [['from' => '09:00', 'to' => '12:00'], ['from' => '12:00', 'to' => '16:00']],
            ],
            ['date' => '2026-10-02', 'windows' => []],
        ], $times);
    }

    public function testACollectivePickupIsBookedForTheShipmentsAwaitingOne(): void
    {
        $this->bookable = ['18015-1'];
        $waiting = $this->shipment('18015-1');
        $other = $this->shipment('18015-2');

        $result = $this->pickups()->bookCollective([
            $waiting,
            $other,
            $this->shipment('18015-3', booked: true),
            $this->shipment('18015-4', multiPickup: false),
            $this->shipment(null, state: ShipmentInterface::STATE_UNKNOWN),
        ], '2026-10-01', '10:00', '16:00');

        self::assertSame(['18015-1', '18015-2'], $this->booking?->getOrders());
        self::assertSame('2026-10-01 10:00', $this->booking->getPickupTimeFrom()->format('Y-m-d H:i'));
        self::assertSame('2026-10-01 16:00', $this->booking->getPickupTimeTo()->format('Y-m-d H:i'));
        self::assertSame(['booked' => ['18015-1'], 'failed' => ['18015-2' => 'Za późno']], $result);
        self::assertSame(['18015-1'], $this->saved, 'Only the booked one is marked.');
        self::assertTrue($waiting->isPickupBooked());
        self::assertFalse($other->isPickupBooked());

        $event = $this->events->of(ApiEventEnum::PickupCreateAfter);
        self::assertCount(1, $event);
        self::assertSame([$waiting], $event[0]['shipments']);
    }

    public function testTheSenderPostcodeIsTheChosenAddresssOrTheOneTypedIn(): void
    {
        $pickups = $this->pickups();

        self::assertSame('00-950', $pickups->senderPostcodeOf(['order_id' => 29, 'sender_address_id' => '3']));
        self::assertSame(
            '30-001',
            $pickups->senderPostcodeOf([
                'order_id' => 29,
                'sender_address_id' => 'new',
                'new_sender_postcode' => ' 30-001 ',
            ]),
        );
    }

    public function testCollectionTimesWaitForTheSendersPostcode(): void
    {
        $this->expectExceptionMessage('Fill in the sender\'s postcode first.');

        $this->pickups()->senderPostcodeOf([
            'order_id' => 29,
            'sender_address_id' => 'new',
            'new_sender_postcode' => '',
        ]);
    }

    public function testNothingIsBookedWithoutAShipmentAwaitingIt(): void
    {
        $this->expectExceptionMessage('None of the selected shipments awaits a collective pickup.');

        try {
            $this->pickups()->bookCollective(
                [$this->shipment('18015-3', booked: true)],
                '2026-10-01',
                '10:00',
                '16:00',
            );
        } finally {
            self::assertNull($this->booking);
        }
    }

    public function testPolkurierIsAskedToBookWithoutAskingFirstWhetherTheAccountCan(): void
    {
        // Polkurier answers "available" only for a carrier and parcels given, never for an
        // account as a whole: asked without them, it always says no.
        $this->multiPickupAvailable = false;
        $this->bookable = ['18015-1'];

        $result = $this->pickups()->bookCollective(
            [$this->shipment('18015-1')],
            '2026-10-01',
            '10:00',
            '16:00',
        );

        self::assertSame(['18015-1'], $result['booked']);
    }

    public function testWhetherACollectivePickupIsPossibleIsAskedForTheShipment(): void
    {
        $pack = new PackMessage(
            length: 40,
            width: 30,
            height: 20,
            weight: 0.55,
            type: PackTypeEnum::Standard,
            amount: 1,
        );

        self::assertTrue($this->pickups()->isCollectiveAvailable('DPD', '31-042', 'box', [$pack]));
        self::assertSame('DPD', $this->availabilityAsked?->getCourier());
        self::assertSame('31-042', $this->availabilityAsked->getSenderPostCode());
        self::assertSame(ShipmentTypeEnum::Box, $this->availabilityAsked->getShipmentType());
        self::assertSame([$pack], $this->availabilityAsked->getPacks());
    }

    public function testABookingPolkurierTurnsDownEntirelyIsNotReported(): void
    {
        $result = $this->pickups()->bookCollective(
            [$this->shipment('18015-1')],
            '2026-10-01',
            '10:00',
            '16:00',
        );

        self::assertSame([], $result['booked']);
        self::assertSame([], $this->saved);
        self::assertSame([], $this->events->of(ApiEventEnum::PickupCreateAfter));
    }

    protected function setUp(): void
    {
        $this->events = new RecordingEventManager();
    }

    private function pickups(): PickupService
    {
        $gateway = $this->createStub(ShipmentGateway::class);
        $gateway->method('getPickupTimes')->willReturnCallback(function (GetPickupTimeMessage $message): array {
            $this->timesAsked = $message;

            return [
                new PickupTimeMessage(new \DateTimeImmutable('2026-10-01'), [
                    new TimeIntervalMessage(
                        new \DateTimeImmutable('2026-10-01 09:00'),
                        new \DateTimeImmutable('2026-10-01 12:00'),
                    ),
                    new TimeIntervalMessage(
                        new \DateTimeImmutable('2026-10-01 12:00'),
                        new \DateTimeImmutable('2026-10-01 16:00'),
                    ),
                ]),
                new PickupTimeMessage(new \DateTimeImmutable('2026-10-02')),
            ];
        });
        $gateway->method('isMultiPickupAvailable')->willReturnCallback(
            function (MultiPickupAvailabilityRequestMessage $message): bool {
                $this->availabilityAsked = $message;

                return $this->multiPickupAvailable;
            },
        );
        $gateway->method('bookMultiPickup')->willReturnCallback(
            function (MultiPickupRequestMessage $message): MultiPickupResultMessage {
                $this->booking = $message;
                $failed = [];
                foreach (\array_diff($message->getOrders(), $this->bookable) as $number) {
                    $failed[$number] = 'Za późno';
                }

                return new MultiPickupResultMessage($this->bookable, $failed);
            },
        );
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(function (ShipmentInterface $shipment): ShipmentInterface {
            $this->saved[] = (string)$shipment->getPolkurierNumber();

            return $shipment;
        });

        $orders = $this->createStub(OrderLoader::class);
        $orders->method('getForPolkurier')->willReturn($this->createStub(Order::class));
        $addresses = $this->createStub(AddressRepositoryInterface::class);
        $addresses->method('get')->willReturnCallback(function (): AddressInterface {
            $address = $this->createStub(AddressInterface::class);
            $address->method('getPostcode')->willReturn('00-950');

            return $address;
        });

        return new PickupService(
            $gateway,
            $repository,
            new ApiEventDispatcher($this->events, new NullLogger()),
            $orders,
            $addresses,
            $this->createStub(SenderMapper::class),
            NewingObjectManager::factory(GetPickupTimeMessageBuilderFactory::class),
            NewingObjectManager::factory(MultiPickupAvailabilityRequestMessageBuilderFactory::class),
            NewingObjectManager::factory(MultiPickupRequestMessageBuilderFactory::class),
        );
    }

    private function shipment(
        ?string $number,
        bool $booked = false,
        bool $multiPickup = true,
        string $state = ShipmentInterface::STATE_CREATED,
    ): ShipmentInterface {
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getPolkurierNumber')->willReturn($number);
        $shipment->method('getStoreId')->willReturn(1);
        $shipment->method('getState')->willReturn($state);
        $shipment->method('isMultiPickup')->willReturn($multiPickup);
        $shipment->method('isPickupBooked')->willReturnCallback(static function () use (&$booked): bool {
            return $booked;
        });
        $shipment->method('setPickupBooked')->willReturnCallback(
            static function (bool $value) use (&$booked, &$shipment): ShipmentInterface {
                $booked = $value;

                return $shipment;
            },
        );

        return $shipment;
    }
}
