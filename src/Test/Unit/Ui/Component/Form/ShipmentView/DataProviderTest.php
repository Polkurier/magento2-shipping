<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Form\ShipmentView;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Details\DetailsLoader;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderApplier;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Shipping\Ui\Component\Form\ShipmentView\DataProvider;
use Polkurier\Shipping\ViewModel\Adminhtml\ShipmentDetails;

#[CoversClass(DataProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class DataProviderTest extends TestCase
{
    private const int NOW = 1791280800;

    public function testThePageIsBuiltFromWhatIsStoredWithoutAskingPolkurier(): void
    {
        $shipment = $this->createShipment();
        $statusRefresh = $this->createMock(StatusRefreshService::class);
        $statusRefresh->expects(self::never())->method('refresh');
        $gateway = $this->createMock(ShipmentGateway::class);
        $gateway->expects(self::never())->method('getStatus');
        $details = $this->createMock(ShipmentDetails::class);
        $details->expects(self::once())->method('buildPage')->with($shipment, [])->willReturn(['title' => 'Stored']);

        $data = $this->createProvider($shipment, $details, $statusRefresh, $gateway)->getData();

        self::assertSame(['title' => 'Stored'], $data[3]['view']);
        self::assertTrue($data[3]['refresh'], 'The copy is missing: the page has it read in the background.');
        self::assertSame('polkurier/shipment/refreshDetails?id=3', $data[3]['urls']['refresh']);
    }

    public function testAFinishedShipmentWithItsCopyAndScansIsNotRefreshed(): void
    {
        $data = $this->createProvider(
            $this->createShipment()->setDetails(['number' => '18015-2865'])->setIsFinal(true),
        )->getData();

        self::assertFalse($data[3]['refresh']);
    }

    public function testAShipmentOnItsWayIsRefreshedEachTime(): void
    {
        $data = $this->createProvider($this->createShipment()->setDetails(['number' => '18015-2865']))->getData();

        self::assertTrue($data[3]['refresh']);
    }

    public function testACancelledShipmentWithFinalScansIsNotRefreshed(): void
    {
        $data = $this->createProvider(
            $this->createShipment()
                ->setDetails(['number' => '18015-2865'])
                ->setState(ShipmentInterface::STATE_CANCELLED)
                ->setIsFinal(true)
                ->setTrackingSyncedAt(\gmdate('Y-m-d H:i:s', self::NOW - 3 * 24 * 60 * 60)),
        )->getData();

        self::assertFalse($data[3]['refresh']);
    }

    public function testAShipmentWithoutAPolkurierOrderIsNotRefreshed(): void
    {
        $data = $this->createProvider(
            $this->createShipment()->setPolkurierNumber(null)->setState(ShipmentInterface::STATE_FAILED),
        )->getData();

        self::assertFalse($data[3]['refresh']);
    }

    private function createProvider(
        Shipment $shipment,
        ?ShipmentDetails $details = null,
        ?StatusRefreshService $statusRefresh = null,
        ?ShipmentGateway $gateway = null,
    ): DataProvider {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['id', null, '3']]);
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('get')->willReturn($shipment);
        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturn($this->createStub(Collection::class));
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $path, array $params = []): string => $path . '?' . \http_build_query($params),
        );

        return new DataProvider(
            'polkurier_shipment_view_data_source',
            'entity_id',
            'id',
            $collections,
            $request,
            $repository,
            new DetailsLoader(
                $statusRefresh ?? $this->createStub(StatusRefreshService::class),
                $this->createStub(RemoteOrderApplier::class),
                $this->createStub(ShipmentResource::class),
                $gateway ?? $this->createStub(ShipmentGateway::class),
                new FixedDateTime(self::NOW),
                $this->createStub(Logger::class),
            ),
            $details ?? $this->createStub(ShipmentDetails::class),
            $url,
        );
    }

    private function createShipment(): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class, ['json' => new Json()]);

        return $shipment->setId(3)
            ->setState(ShipmentInterface::STATE_CREATED)
            ->setPolkurierNumber('18015-2865')
            ->setDetailsSyncedAt(\gmdate('Y-m-d H:i:s', self::NOW))
            ->setTrackingSyncedAt(\gmdate('Y-m-d H:i:s', self::NOW));
    }
}
