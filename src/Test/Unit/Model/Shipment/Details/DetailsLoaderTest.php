<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Details;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Details\DetailsLoader;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderApplier;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderMatcher;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Webservice\Response\Order;
use Polkurier\Webservice\Response\Order\OrderStatusMessage;

#[CoversClass(DetailsLoader::class)]
#[AllowMockObjectsWithoutExpectations]
class DetailsLoaderTest extends TestCase
{
    private const int NOW = 1791280800;

    private const array SCANS = [
        [
            'date' => '2026-10-06 08:00:00',
            'status_code' => 'D',
            'status' => 'Doręczona',
            'location' => 'Warszawa',
            'description' => '',
        ],
    ];

    /**
     * @var string[] the Polkurier numbers whose scans were read, in order
     */
    private array $read = [];

    /**
     * @var array<int, array<string, mixed>> the columns written, by shipment id
     */
    private array $written = [];

    private ?\Throwable $readFailure = null;
    private ?\Throwable $writeFailure = null;
    private ?\Throwable $orderFailure = null;

    public function testAMissingOrderCopyIsReadForThePageOnly(): void
    {
        $reconciler = $this->createStub(RemoteOrderMatcher::class);
        $reconciler->method('findByNumber')->willReturn($this->createStub(Order::class));
        $snapshot = $this->createStub(RemoteOrderApplier::class);
        $snapshot->method('apply')->willReturnCallback(
            static function (Order $order, ShipmentInterface $shipment): void {
                $shipment->setStatusLabel('Doręczona');
            },
        );
        $resource = $this->createMock(ShipmentResource::class);
        $resource->expects(self::never())->method('updateColumns');
        $stored = $this->shipment();

        $loaded = (new DetailsLoader(
            $reconciler,
            $snapshot,
            $resource,
            $this->createStub(ShipmentGateway::class),
            new FixedDateTime(self::NOW),
            $this->createStub(Logger::class),
        ))->ensure($stored);

        self::assertSame([], $loaded['errors']);
        self::assertSame('Doręczona', $loaded['shipment']->getStatusLabel());
        self::assertNull($stored->getStatusLabel(), 'Nothing is stored, and the loaded row is left as it was.');
    }

    public function testScansOlderThanAnHourAreReadAgain(): void
    {
        $loaded = $this->loader()->ensure(
            $this->shipment()->setDetails(['number' => '18015-2865'])->setTrackingSyncedAt($this->minutesAgo(61)),
        );

        self::assertSame(['18015-2865'], $this->read);
        self::assertSame([], $loaded['errors']);
        self::assertSame(self::SCANS, $loaded['shipment']->getTracking());
    }

    public function testScansAnHourOldAreStillTrusted(): void
    {
        $this->loader()->ensure(
            $this->shipment()->setDetails(['number' => '18015-2865'])->setTrackingSyncedAt($this->minutesAgo(60)),
        );

        self::assertSame([], $this->read);
        self::assertSame([], $this->written);
    }

    public function testTheScansOfAFinishedShipmentAreNotReadAgain(): void
    {
        $this->loader()->ensure(
            $this->shipment()
                ->setDetails(['number' => '18015-2865'])
                ->setIsFinal(true)
                ->setTrackingSyncedAt($this->minutesAgo(3 * 24 * 60)),
        );

        self::assertSame([], $this->read, 'Its scans cannot move on any more.');
    }

    public function testScansNeverReadAreReadEvenForAFinishedShipment(): void
    {
        $this->loader()->ensure(
            $this->shipment()->setDetails(['number' => '18015-2865'])->setIsFinal(true)->setTrackingSyncedAt(null),
        );

        self::assertSame(['18015-2865'], $this->read);
    }

    public function testAShipmentNotPlacedHasNoScansToRead(): void
    {
        $notPlaced = [
            ShipmentInterface::STATE_PENDING,
            ShipmentInterface::STATE_UNKNOWN,
            ShipmentInterface::STATE_FAILED,
            ShipmentInterface::STATE_CANCELLED,
        ];
        foreach ($notPlaced as $state) {
            $this->loader()->ensure(
                $this->shipment()->setDetails(['number' => '18015-2865'])->setState($state)->setTrackingSyncedAt(null),
            );
        }

        self::assertSame([], $this->read);
    }

    public function testRefreshingTheScansWritesOnlyTheirColumns(): void
    {
        $shipment = $this->shipment()->setStatusLabel('Changed on the page')->setTrackingSyncedAt($this->minutesAgo(5));

        $this->loader()->refreshTracking($shipment);

        self::assertSame([3], \array_keys($this->written));
        self::assertSame(
            [ShipmentInterface::FIELD_TRACKING, ShipmentInterface::FIELD_TRACKING_SYNCED_AT],
            \array_keys($this->written[3]),
            'The rest of the row is left as stored.',
        );
        self::assertSame(self::SCANS, \json_decode((string)$this->written[3][ShipmentInterface::FIELD_TRACKING], true));
        self::assertSame($this->minutesAgo(0), $this->written[3][ShipmentInterface::FIELD_TRACKING_SYNCED_AT]);
        self::assertSame(self::SCANS, $shipment->getTracking(), 'The shipment shown has them too.');
    }

    public function testACancelledShipmentOrOneWithoutANumberHasNoScansToRefresh(): void
    {
        $this->loader()->refreshTracking($this->shipment()->setState(ShipmentInterface::STATE_CANCELLED));
        $this->loader()->refreshTracking($this->shipment()->setPolkurierNumber(null));

        self::assertSame([], $this->read);
        self::assertSame([], $this->written);
    }

    public function testScansPolkurierCannotGiveAreReportedWithItsReason(): void
    {
        $this->readFailure = new LocalizedException(__('Polkurier is not answering.'));

        $loaded = $this->loader()->ensure(
            $this->shipment()->setDetails(['number' => '18015-2865'])->setTrackingSyncedAt(null),
        );

        self::assertSame(['The carrier\'s scans could not be read: Polkurier is not answering.'], $loaded['errors']);
        self::assertSame([], $this->written);
    }

    public function testAnUnexpectedFailureWithTheScansIsReportedPlainly(): void
    {
        $this->writeFailure = new \RuntimeException('Deadlock found when trying to get lock.');

        $loaded = $this->loader()->ensure(
            $this->shipment()->setDetails(['number' => '18015-2865'])->setTrackingSyncedAt(null),
        );

        self::assertSame(
            ['The carrier\'s scans could not be read.'],
            $loaded['errors'],
            'No database detail on the page.',
        );
    }

    public function testAnOrderCopyPolkurierCannotGiveIsReportedWithItsReason(): void
    {
        $this->orderFailure = new LocalizedException(__('Polkurier has no order number 18015-2865 on this account.'));
        $stored = $this->shipment();

        $loaded = $this->loader()->ensure($stored);

        self::assertSame(['Polkurier has no order number 18015-2865 on this account.'], $loaded['errors']);
        self::assertSame($stored, $loaded['shipment'], 'The page shows what is stored.');
    }

    public function testAnUnexpectedFailureWithTheOrderCopyIsReportedPlainlyAndTheScansStillRead(): void
    {
        $this->orderFailure = new \TypeError('Unexpected answer.');

        $loaded = $this->loader()->ensure($this->shipment()->setTrackingSyncedAt(null));

        self::assertSame(['The Polkurier order could not be read.'], $loaded['errors']);
        self::assertSame(['18015-2865'], $this->read);
    }

    public function testAShipmentWithoutAPolkurierNumberHasNothingToLoad(): void
    {
        $loaded = $this->loader()->ensure($this->shipment()->setPolkurierNumber(null)->setTrackingSyncedAt(null));

        self::assertSame([], $loaded['errors']);
        self::assertSame([], $this->read);
    }

    private function loader(): DetailsLoader
    {
        $matcher = $this->createStub(RemoteOrderMatcher::class);
        $matcher->method('findByNumber')->willReturnCallback(function (): Order {
            if ($this->orderFailure !== null) {
                throw $this->orderFailure;
            }

            return $this->createStub(Order::class);
        });
        $applier = $this->createStub(RemoteOrderApplier::class);
        $applier->method('tracking')->willReturn(self::SCANS);
        $gateway = $this->createStub(ShipmentGateway::class);
        $gateway->method('getStatus')->willReturnCallback(
            function (string $number, bool $tracking): OrderStatusMessage {
                self::assertTrue($tracking, 'Read with the scans.');
                if ($this->readFailure !== null) {
                    throw $this->readFailure;
                }
                $this->read[] = $number;

                return $this->createStub(OrderStatusMessage::class);
            },
        );
        $resource = $this->createStub(ShipmentResource::class);
        $resource->method('updateColumns')->willReturnCallback(function (int $id, array $values): void {
            if ($this->writeFailure !== null) {
                throw $this->writeFailure;
            }
            $this->written[$id] = $values;
        });

        return new DetailsLoader(
            $matcher,
            $applier,
            $resource,
            $gateway,
            new FixedDateTime(self::NOW),
            $this->createStub(Logger::class),
        );
    }

    private function shipment(): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);

        return $shipment->setId(3)
            ->setState(ShipmentInterface::STATE_CREATED)
            ->setPolkurierNumber('18015-2865')
            // Scans read just now: only the order is read again.
            ->setTrackingSyncedAt(\gmdate('Y-m-d H:i:s', self::NOW));
    }

    private function minutesAgo(int $minutes): string
    {
        return \gmdate('Y-m-d H:i:s', self::NOW - $minutes * 60);
    }
}
