<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\RefreshDetails;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\View;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Details\DetailsLoader;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderApplier;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;
use Polkurier\Shipping\Test\Unit\Support\AdminActionTestCase;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Shipping\ViewModel\Adminhtml\ShipmentDetails;
use Polkurier\Webservice\Response\Order\OrderStatusMessage;

/**
 * The shipment details page's background refresh: what it reads from Polkurier, what it stores,
 * and what it answers the page with.
 */
#[CoversClass(RefreshDetails::class)]
#[AllowMockObjectsWithoutExpectations]
class RefreshDetailsTest extends AdminActionTestCase
{
    private const int NOW = 1791280800;

    /**
     * @var string[] the Polkurier numbers whose scans were read
     */
    private array $scansRead = [];

    /**
     * @var array<int, array<string, mixed>> the columns written, by shipment id
     */
    private array $written = [];

    private ?\Throwable $scansFailure = null;

    public function testItIsGuardedLikeTheDetailsPage(): void
    {
        self::assertSame(View::ADMIN_RESOURCE, RefreshDetails::ADMIN_RESOURCE);
    }

    public function testAMissingOrderCopyIsStoredByAStatusRefreshAndShown(): void
    {
        $refreshed = $this->shipment()->setDetails(['number' => '18015-2865']);
        $statusRefresh = $this->createMock(StatusRefreshService::class);
        $statusRefresh->expects(self::once())->method('refresh')->with(3)->willReturn($refreshed);

        $this->action($this->shipment(), $statusRefresh)->execute();

        self::assertFalse($this->json['error']);
        self::assertSame([], $this->json['errors']);
        self::assertSame(['built from' => $refreshed, 'errors' => []], $this->json['view']);
        self::assertSame($refreshed->getState(), $this->json['state'], 'A changed state reloads the page.');
    }

    public function testScansNeverReadOfAFinishedShipmentAreReadAndStored(): void
    {
        $statusRefresh = $this->createMock(StatusRefreshService::class);
        $statusRefresh->expects(self::never())->method('refresh');
        $shipment = $this->shipment()
            ->setDetails(['number' => '18015-2865'])
            ->setIsFinal(true)
            ->setTrackingSyncedAt(null);

        $this->action($shipment, $statusRefresh)->execute();

        self::assertSame(['18015-2865'], $this->scansRead);
        self::assertSame([3], \array_keys($this->written));
        self::assertSame($shipment, $this->json['view']['built from']);
    }

    public function testWhatPolkurierCannotGiveIsAnsweredWithoutAView(): void
    {
        $statusRefresh = $this->createStub(StatusRefreshService::class);
        $statusRefresh->method('refresh')
            ->willThrowException(new LocalizedException(__('Polkurier is not answering.')));
        $this->scansFailure = new LocalizedException(__('Polkurier is not answering.'));

        $this->action($this->shipment()->setTrackingSyncedAt(null), $statusRefresh)->execute();

        self::assertTrue($this->json['error']);
        self::assertArrayNotHasKey('view', $this->json);
        self::assertSame(
            [
                'Polkurier is not answering.',
                'The carrier\'s scans could not be read: Polkurier is not answering.',
            ],
            $this->json['errors'],
        );
        self::assertSame([], $this->written);
    }

    public function testAPartReadIsShownWithWhyTheOtherIsNot(): void
    {
        $statusRefresh = $this->createStub(StatusRefreshService::class);
        $statusRefresh->method('refresh')->willThrowException(new \TypeError('Unexpected answer.'));

        $this->action($this->shipment()->setTrackingSyncedAt(null), $statusRefresh)->execute();

        self::assertFalse($this->json['error']);
        self::assertSame(['The Polkurier order could not be read.'], $this->json['errors']);
        self::assertSame(['The Polkurier order could not be read.'], $this->json['view']['errors']);
    }

    public function testAShipmentThatIsGoneIsAnsweredWithAnError(): void
    {
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException(__('The shipment is gone.')));

        (new RefreshDetails(
            $this->context(),
            $this->jsonFactory(),
            $repository,
            $this->createStub(DetailsLoader::class),
            $this->createStub(ShipmentDetails::class),
        ))->execute();

        self::assertSame(
            ['error' => true, 'message' => 'The shipment is gone.', 'errors' => ['The shipment is gone.']],
            $this->json,
        );
    }

    private function action(Shipment $shipment, StatusRefreshService $statusRefresh): RefreshDetails
    {
        $this->params = ['id' => '3'];
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('get')->willReturn($shipment);
        $gateway = $this->createStub(ShipmentGateway::class);
        $gateway->method('getStatus')->willReturnCallback(function (string $number): OrderStatusMessage {
            if ($this->scansFailure !== null) {
                throw $this->scansFailure;
            }
            $this->scansRead[] = $number;

            return $this->createStub(OrderStatusMessage::class);
        });
        $resource = $this->createStub(ShipmentResource::class);
        $resource->method('updateColumns')->willReturnCallback(function (int $id, array $values): void {
            $this->written[$id] = $values;
        });
        $details = $this->createStub(ShipmentDetails::class);
        $details->method('build')->willReturnCallback(
            static fn(ShipmentInterface $built, array $errors): array => ['built from' => $built, 'errors' => $errors],
        );

        return new RefreshDetails(
            $this->context(),
            $this->jsonFactory(),
            $repository,
            new DetailsLoader(
                $statusRefresh,
                $this->createStub(RemoteOrderApplier::class),
                $resource,
                $gateway,
                new FixedDateTime(self::NOW),
                $this->createStub(Logger::class),
            ),
            $details,
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
}
