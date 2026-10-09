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
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
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

    /**
     * @var int[] the ids of the shipments whose status was refreshed, in order
     */
    private array $statusRefreshed = [];

    private ?ShipmentInterface $refreshedShipment = null;

    public function testAMissingOrderCopyIsStoredByAStatusRefresh(): void
    {
        $refreshed = $this->shipment()->setDetails(['number' => '18015-2865'])->setStatusLabel('Doręczona');
        $this->refreshedShipment = $refreshed;

        $loaded = $this->loader()->refresh($this->shipment());

        self::assertSame([3], $this->statusRefreshed);
        self::assertSame([], $loaded['errors']);
        self::assertSame($refreshed, $loaded['shipment'], 'The shipment as the refresh stored it.');
        self::assertSame(['18015-2865'], $this->read, 'And its scans.');
    }

    public function testTheScansOfTheRefreshedShipmentFollowItsStateNow(): void
    {
        $this->refreshedShipment = $this->shipment()
            ->setDetails(['number' => '18015-2865'])
            ->setState(ShipmentInterface::STATE_CANCELLED)
            ->setTrackingSyncedAt(null);

        $this->loader()->refresh($this->shipment()->setTrackingSyncedAt(null));

        self::assertSame([3], $this->statusRefreshed);
        self::assertSame([], $this->read, 'Cancelled in Polkurier: no scans to read.');
    }

    public function testAFinishedShipmentWithItsCopyAndScansNeedsNoRefresh(): void
    {
        $shipment = $this->shipment()->setDetails(['number' => '18015-2865'])->setIsFinal(true);

        self::assertFalse($this->loader()->needsRefresh($shipment));
        $loaded = $this->loader()->refresh($shipment);

        self::assertSame($shipment, $loaded['shipment']);
        self::assertSame([], $this->statusRefreshed);
        self::assertSame([], $this->read);
    }

    public function testAShipmentOnItsWayIsRefreshedEachTime(): void
    {
        self::assertTrue($this->loader()->needsRefresh($this->shipment()), 'No copy yet.');
        self::assertTrue(
            $this->loader()->needsRefresh($this->shipment()->setDetails(['number' => '18015-2865'])),
            'Read just now, and may have moved on since.',
        );
    }

    public function testACancelledShipmentWithItsCopyNeedsNoRefresh(): void
    {
        self::assertFalse($this->loader()->needsRefresh(
            $this->shipment()
                ->setDetails(['number' => '18015-2865'])
                ->setState(ShipmentInterface::STATE_CANCELLED)
                ->setTrackingSyncedAt($this->minutesAgo(3 * 24 * 60)),
        ));
    }

    public function testAShipmentOnItsWayHasItsCopyAndScansReadAgain(): void
    {
        $loaded = $this->loader()->refresh($this->shipment()->setDetails(['number' => '18015-2865']));

        self::assertSame([3], $this->statusRefreshed);
        self::assertSame(['18015-2865'], $this->read);
        self::assertSame([], $loaded['errors']);
        self::assertSame(self::SCANS, $loaded['shipment']->getTracking());
    }

    public function testTheScansOfAFinishedShipmentAreNotReadAgain(): void
    {
        $this->loader()->refresh(
            $this->shipment()
                ->setDetails(['number' => '18015-2865'])
                ->setIsFinal(true)
                ->setTrackingSyncedAt($this->minutesAgo(3 * 24 * 60)),
        );

        self::assertSame([], $this->read, 'Its scans cannot move on any more.');
    }

    public function testScansNeverReadAreReadEvenForAFinishedShipment(): void
    {
        $this->loader()->refresh(
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
            $this->loader()->refresh(
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

        $loaded = $this->loader()->refresh(
            $this->shipment()->setDetails(['number' => '18015-2865'])->setIsFinal(true)->setTrackingSyncedAt(null),
        );

        self::assertSame(['The carrier\'s scans could not be read: Polkurier is not answering.'], $loaded['errors']);
        self::assertSame([], $this->written);
        self::assertNull($loaded['shipment'], 'Nothing was read.');
    }

    public function testAnUnexpectedFailureWithTheScansIsReportedPlainly(): void
    {
        $this->writeFailure = new \RuntimeException('Deadlock found when trying to get lock.');

        $loaded = $this->loader()->refresh(
            $this->shipment()->setDetails(['number' => '18015-2865'])->setIsFinal(true)->setTrackingSyncedAt(null),
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
        // Finished, with its scans read: only the copy is read.
        $stored = $this->shipment()->setIsFinal(true);

        $loaded = $this->loader()->refresh($stored);

        self::assertSame(['Polkurier has no order number 18015-2865 on this account.'], $loaded['errors']);
        self::assertNull($loaded['shipment'], 'Nothing was read: the page keeps what it shows.');
        self::assertSame([], $stored->getDetails());
    }

    public function testAnUnexpectedFailureWithTheOrderCopyIsReportedPlainlyAndTheScansStillRead(): void
    {
        $this->orderFailure = new \TypeError('Unexpected answer.');

        $loaded = $this->loader()->refresh($this->shipment()->setTrackingSyncedAt(null));

        self::assertSame(['The Polkurier order could not be read.'], $loaded['errors']);
        self::assertSame(['18015-2865'], $this->read);
        self::assertNotNull($loaded['shipment'], 'The scans were read and stored.');
    }

    public function testAShipmentWithoutAPolkurierNumberHasNothingToLoad(): void
    {
        $loaded = $this->loader()->refresh($this->shipment()->setPolkurierNumber(null)->setTrackingSyncedAt(null));

        self::assertSame([], $loaded['errors']);
        self::assertSame([], $this->read);
        self::assertSame([], $this->statusRefreshed);
    }

    private function loader(): DetailsLoader
    {
        $statusRefresh = $this->createStub(StatusRefreshService::class);
        $statusRefresh->method('refresh')->willReturnCallback(function (int $id): ShipmentInterface {
            if ($this->orderFailure !== null) {
                throw $this->orderFailure;
            }
            $this->statusRefreshed[] = $id;

            return $this->refreshedShipment ?? $this->shipment()->setDetails(['number' => '18015-2865']);
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
            $statusRefresh,
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
            ->setDetailsSyncedAt(\gmdate('Y-m-d H:i:s', self::NOW))
            ->setTrackingSyncedAt(\gmdate('Y-m-d H:i:s', self::NOW));
    }

    private function minutesAgo(int $minutes): string
    {
        return \gmdate('Y-m-d H:i:s', self::NOW - $minutes * 60);
    }
}
