<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Status;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderApplier;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Request\Order\GetOrdersMessage;
use Polkurier\Webservice\Response\Order;
use Polkurier\Webservice\Testing\RecordingClient;

#[CoversClass(RemoteOrderApplier::class)]
#[AllowMockObjectsWithoutExpectations]
class RemoteOrderApplierTest extends SdkTestCase
{
    private const string NOW = '2026-09-25 12:00:00';

    /**
     * @return array<string, array{string, ?string, ?string, bool}>
     */
    public static function shipments(): array
    {
        return [
            'in transit' => ['WP', null, '2026-09-01 10:00:00', false],
            'delivered without COD' => ['D', null, '2026-09-24 10:00:00', true],
            'delivered, COD still with the carrier' => ['D', 'IN_TRANSIT', '2026-09-24 10:00:00', false],
            'delivered, COD in the money box' => ['D', 'MONEY_BOX', '2026-09-24 10:00:00', false],
            'delivered, COD paid out' => ['D', 'PAID', '2026-09-24 10:00:00', true],
            'delivered, COD offset' => ['D', 'COMPENSATION', '2026-09-24 10:00:00', true],
            'returning since yesterday' => ['Z', 'WAITING', '2026-09-24 10:00:00', false],
            'returning for 15 days' => ['Z', 'WAITING', '2026-09-10 10:00:00', true],
            'returning, no status date' => ['Z', null, null, false],
            'carrier exception' => ['W', null, '2026-08-01 10:00:00', false],
        ];
    }

    #[DataProvider('shipments')]
    public function testDecidesWhenAShipmentIsFinished(
        string $status,
        ?string $cod,
        ?string $statusDate,
        bool $final,
    ): void {
        $shipment = $this->shipment()->setStatusCode($status)->setCodStatus($cod)->setStatusDate($statusDate);

        self::assertSame(
            $final,
            (new RemoteOrderApplier(new FixedDateTime()))->isFinal($shipment, new \DateTimeImmutable(self::NOW)),
        );
    }

    public function testAppliesThePolkurierOrderToTheShipment(): void
    {
        $shipment = $this->shipment()
            ->setState(ShipmentInterface::STATE_UNKNOWN)
            ->setTrackingUrl('https://kept.example');

        (new RemoteOrderApplier(new FixedDateTime((int)\strtotime(self::NOW . ' UTC'))))
            ->apply($this->order(), $shipment);

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState(), 'Found, so placed after all.');
        self::assertSame('12345-678', $shipment->getPolkurierNumber());
        self::assertSame(['6211000015757'], $shipment->getWaybills());
        self::assertSame(['D', 'Dostarczone'], [$shipment->getStatusCode(), $shipment->getStatusLabel()]);
        self::assertSame(
            '2026-06-25 15:20:00',
            $shipment->getStatusDate(),
            'Reported at 17:20 Polish time: stored in UTC.',
        );
        self::assertSame(self::NOW, $shipment->getDetailsSyncedAt());
        self::assertSame('12345-678', $shipment->getDetails()['number'] ?? null, 'The copy the details page shows.');
        self::assertNotNull($shipment->getTrackingUrl());
    }

    public function testACancelledPolkurierOrderCancelsTheShipment(): void
    {
        $json = (string)\json_encode(\array_replace_recursive(
            \json_decode(self::fixture('get_orders.json'), true),
            ['response' => ['result' => [['status_code' => 'A', 'status' => 'Anulowane', 'COD' => null]]]],
        ));
        $shipment = $this->shipment()->setState(ShipmentInterface::STATE_CREATED);

        (new RemoteOrderApplier(new FixedDateTime()))->apply($this->order($json), $shipment);

        self::assertSame(ShipmentInterface::STATE_CANCELLED, $shipment->getState());
        self::assertTrue($shipment->isFinal());
    }

    public function testACancelledShipmentIsFinished(): void
    {
        $shipment = $this->shipment()->setState(ShipmentInterface::STATE_CANCELLED)->setStatusCode('OC');

        self::assertTrue((new RemoteOrderApplier(new FixedDateTime()))->isFinal($shipment));
    }

    private function shipment(): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);

        return $shipment->setState(ShipmentInterface::STATE_CREATED);
    }

    private function order(?string $answer = null): Order
    {
        $http = new RecordingClient($answer ?? self::fixture('get_orders.json'));

        return $this->clientPool($http)
            ->orders()
            ->getOrders(new GetOrdersMessage(searchPhrase: '12345-678'))
            ->getResult()[0];
    }
}
