<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Model\Shipment\OrderDetails;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Request\Order\GetOrdersMessage;
use Polkurier\Webservice\Request\Order\GetStatusMessage;
use Polkurier\Webservice\Testing\RecordingClient;

#[CoversClass(OrderDetails::class)]
class OrderDetailsTest extends SdkTestCase
{
    public function testOrderIsCopiedWithAddressesPacksCodAndCost(): void
    {
        $http = new RecordingClient(self::fixture('get_orders.json'));
        $order = $this->clientPool($http)->orders()->getOrders(new GetOrdersMessage(orderNo: '12345-678'))->getResult()[0];

        $details = (new OrderDetails())->fromOrder($order);

        self::assertSame('12345-678', $details['number']);
        self::assertSame('2026-06-20 10:59:43', $details['date']);
        self::assertSame('box', $details['shipment_type']);
        self::assertSame([['number' => '6211000015757', 'is_default' => true]], $details['waybills']);
        self::assertSame('Anna Odbiorca', $details['recipient']['person']);
        self::assertSame('00-001', $details['recipient']['postcode']);
        self::assertNull($details['recipient']['point_id']);
        self::assertSame(['amount' => 420.0, 'type' => 'S', 'return_type' => 'BA', 'bank_account' => '', 'status' => 'PAID'], $details['cod']);
        self::assertSame('2026-06-21', $details['pickup']['date']);
        self::assertSame('10:00', $details['pickup']['time_from']);
        self::assertSame(['Pobranie (COD)', 'Ubezpieczenie przesyłki'], $details['services']);
        self::assertSame(175, $details['packs'][0]['length']);
        self::assertSame('ST', $details['packs'][0]['type']);
        self::assertSame('Paczka nr 1', $details['items'][0]['description']);
        self::assertSame(146.37, $details['price_gross']);
        self::assertTrue($details['is_paid']);
        self::assertNotFalse(\json_encode($details), 'The copy is stored as JSON.');
    }

    public function testScansAreListedNewestFirst(): void
    {
        $http = new RecordingClient(self::success([
            'status' => 'Dostarczone',
            'status_code' => 'D',
            'status_date' => '2026-06-25 17:20',
            'delivered_date' => '2026-06-25',
            'url' => 'https://tracking.example/1',
            'tracking' => [
                ['status_date' => '2026-06-24 08:00:00', 'status_code' => 'W', 'status' => 'W drodze',
                    'tracking_status' => 'IN_TRANSIT', 'location' => 'Łódź', 'description' => 'Przyjęta w sortowni'],
                ['status_date' => '2026-06-25 09:00:00', 'status_code' => 'D', 'status' => 'Dostarczone',
                    'tracking_status' => 'DELIVERED', 'location' => 'Warszawa', 'description' => 'Przesyłka doręczona'],
            ],
        ]));
        $status = $this->clientPool($http)->orders()->getStatus(new GetStatusMessage('12345-678', tracking: true));

        $tracking = (new OrderDetails())->tracking($status);

        self::assertSame(['2026-06-25 09:00:00', '2026-06-24 08:00:00'], \array_column($tracking, 'date'));
        self::assertSame('Warszawa', $tracking[0]['location']);
        self::assertSame('Przesyłka doręczona', $tracking[0]['description']);
    }

    public function testAScanPolkurierDoesntDescribeIsDescribedInTheCarriersWords(): void
    {
        $http = new RecordingClient(self::success([
            'status' => 'Oczekuje na odbiór',
            'status_code' => 'OC',
            'status_date' => '2026-10-05 12:47',
            'delivered_date' => null,
            'url' => 'https://tracking.example/1',
            'tracking' => [
                ['status_date' => '2026-10-05 12:47:55', 'status_code' => 'OC', 'status' => 'Oczekuje na odbiór',
                    'tracking_status' => 'Przygotowana przez Nadawcę, oczekuje na odbiór', 'location' => null, 'description' => null],
                ['status_date' => '2026-10-05 12:40:00', 'status_code' => 'P', 'status' => 'Przyjęta',
                    'tracking_status' => 'Przyjęta', 'location' => null, 'description' => null],
            ],
        ]));
        $status = $this->clientPool($http)->orders()->getStatus(new GetStatusMessage('12345-678', tracking: true));

        $tracking = (new OrderDetails())->tracking($status);

        self::assertSame('Przygotowana przez Nadawcę, oczekuje na odbiór', $tracking[0]['description']);
        self::assertSame('', $tracking[0]['location']);
        // Not the status again.
        self::assertSame('', $tracking[1]['description']);
    }
}
