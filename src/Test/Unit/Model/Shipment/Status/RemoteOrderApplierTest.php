<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Status;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
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
    public static function provideShipments(): array
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

    #[DataProvider('provideShipments')]
    public function testDecidesWhenAShipmentIsFinished(
        string $status,
        ?string $cod,
        ?string $statusDate,
        bool $final,
    ): void {
        $shipment = $this->createShipment()->setStatusCode($status)->setCodStatus($cod)->setStatusDate($statusDate);

        self::assertSame(
            $final,
            $this->createApplier(new FixedDateTime((int)\strtotime(self::NOW . ' UTC')))->isFinal($shipment),
        );
    }

    public function testAppliesThePolkurierOrderToTheShipment(): void
    {
        $shipment = $this->createShipment()
            ->setState(ShipmentInterface::STATE_UNKNOWN)
            ->setTrackingUrl('https://kept.example');

        $this->createApplier(new FixedDateTime((int)\strtotime(self::NOW . ' UTC')))
            ->apply($this->createOrder(), $shipment);

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
            \json_decode(self::loadFixture('get_orders.json'), true),
            ['response' => ['result' => [['status_code' => 'A', 'status' => 'Anulowane', 'COD' => null]]]],
        ));
        $shipment = $this->createShipment()->setState(ShipmentInterface::STATE_CREATED);

        $this->createApplier(new FixedDateTime())->apply($this->createOrder($json), $shipment);

        self::assertSame(ShipmentInterface::STATE_CANCELLED, $shipment->getState());
        self::assertTrue($shipment->isFinal());
    }

    public function testAWaybillCancelledOnALiveOrderLeavesTheShipmentPlaced(): void
    {
        $json = (string)\json_encode(\array_replace_recursive(
            \json_decode(self::loadFixture('get_orders.json'), true),
            ['response' => ['result' => [['status_code' => 'A', 'status' => 'Anulowane', 'COD' => null]]]],
        ));
        $shipment = $this->createShipment()->setState(ShipmentInterface::STATE_CREATED);

        $this->createApplier(new FixedDateTime(), cancelled: false)->apply($this->createOrder($json), $shipment);

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState(), 'get_status answers for it.');
        self::assertFalse($shipment->isFinal());
    }

    public function testACancellationPolkurierCantConfirmChangesNothingYet(): void
    {
        $json = (string)\json_encode(\array_replace_recursive(
            \json_decode(self::loadFixture('get_orders.json'), true),
            ['response' => ['result' => [['status_code' => 'A', 'status' => 'Anulowane', 'COD' => null]]]],
        ));
        $shipment = $this->createShipment()->setState(ShipmentInterface::STATE_CREATED);

        try {
            $this->createApplier(new FixedDateTime(), cancelled: null)->apply($this->createOrder($json), $shipment);
            self::fail('Checked again with the next update.');
        } catch (LocalizedException $e) {
            self::assertStringStartsWith('Polkurier reports order 12345-678 as cancelled', $e->getMessage());
        }
        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertNull($shipment->getStatusCode(), 'Nothing applied.');
    }

    public function testACancelledShipmentIsFinished(): void
    {
        $shipment = $this->createShipment()->setState(ShipmentInterface::STATE_CANCELLED)->setStatusCode('OC');

        self::assertTrue($this->createApplier(new FixedDateTime())->isFinal($shipment));
    }

    private function createShipment(): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);

        return $shipment->setState(ShipmentInterface::STATE_CREATED);
    }

    private function createOrder(?string $answer = null): Order
    {
        $http = new RecordingClient($answer ?? self::loadFixture('get_orders.json'));

        return $this->createClientPool($http)
            ->getOrders()
            ->getOrders(new GetOrdersMessage(searchPhrase: '12345-678'))
            ->getResult()[0];
    }

    private function createApplier(FixedDateTime $clock, ?bool $cancelled = true): RemoteOrderApplier
    {
        $gateway = $this->createStub(ShipmentGateway::class);
        $gateway->method('isCancelled')->willReturn($cancelled);

        return new RemoteOrderApplier($clock, $gateway);
    }
}
