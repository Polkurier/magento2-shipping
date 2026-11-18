<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Details;

use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config\Source\AdditionalService;
use Polkurier\Shipping\Model\Config\Source\CodReturnType;
use Polkurier\Shipping\Model\Config\Source\CodStatus;
use Polkurier\Shipping\Model\Config\Source\CodType;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\Config\Source\ShipmentState;
use Polkurier\Shipping\Model\Config\Source\ShipmentType;
use Polkurier\Shipping\Model\Shipment\Details\OptionLabelProvider;
use Polkurier\Shipping\Model\Shipment\Details\SentOrderReader;
use Polkurier\Shipping\Test\Unit\Support\SdkSerializer;
use Polkurier\Webservice\Dto\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Entity\Order;
use Polkurier\Webservice\Entity\Order\Address;
use Polkurier\Webservice\Entity\Order\Cod;
use Polkurier\Webservice\Entity\Order\PackItem;
use Polkurier\Webservice\Entity\Order\Pickup;
use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;
use Polkurier\Webservice\Enum\Order\CodReturnTypeEnum;
use Polkurier\Webservice\Enum\Order\CodTypeEnum;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;

#[CoversClass(SentOrderReader::class)]
class SentOrderReaderTest extends TestCase
{
    public function testAStoredCreateOrderRequestIsReadInTheShapeOfPolkuriersCopy(): void
    {
        $request = SdkSerializer::create()->encode(new CreateOrderMessage(new Order(
            'DPD',
            ShipmentTypeEnum::Box,
            [new PackItem(40, 30, 20, 2.5, PackTypeEnum::Standard, 2)],
            new Address(
                'Jan Kowalski',
                'Prosta',
                'Warszawa',
                '00-838',
                'jan@example.com',
                '600100200',
                'Sklep Sp. z o.o.',
                '20',
                '5',
            ),
            new Address(
                'Anna Nowak',
                'Długa',
                'Kraków',
                '31-147',
                'anna@example.com',
                '600300400',
                null,
                '7',
                pointId: 'KRA01M',
            ),
            new Cod(149.99, '61109010140000071219812874', CodTypeEnum::OneDay, CodReturnTypeEnum::BankAccount),
            500.0,
            new Address(
                'Magazyn Północ',
                'Składowa 3',
                'Poznań',
                '61-897',
                'magazyn@example.com',
                '600500600',
                'Sklep Sp. z o.o.',
            ),
            new Pickup(
                new \DateTimeImmutable('2026-10-08'),
                new \DateTimeImmutable('2026-10-08 10:00'),
                new \DateTimeImmutable('2026-10-08 16:00'),
            ),
            [AdditionalServiceEnum::SmsOnDispatch, AdditionalServiceEnum::CoverSenderAddress],
            ['external_transport_security' => 'YES'],
            'Order #000000052',
        )));

        self::assertSame([
            'description' => 'Order #000000052',
            'shipment_type' => 'box',
            'sender' => [
                'person' => 'Jan Kowalski',
                'company' => 'Sklep Sp. z o.o.',
                'street' => 'Prosta 20/5',
                'postcode' => '00-838',
                'city' => 'Warszawa',
                'country' => 'PL',
                'phone' => '600100200',
                'email' => 'jan@example.com',
                'point_id' => null,
                'point_label' => null,
            ],
            'recipient' => [
                'person' => 'Anna Nowak',
                'company' => null,
                'street' => 'Długa 7',
                'postcode' => '31-147',
                'city' => 'Kraków',
                'country' => 'PL',
                'phone' => '600300400',
                'email' => 'anna@example.com',
                'point_id' => 'KRA01M',
                'point_label' => null,
            ],
            'cover_address' => [
                'person' => 'Magazyn Północ',
                'company' => 'Sklep Sp. z o.o.',
                'street' => 'Składowa 3',
                'postcode' => '61-897',
                'city' => 'Poznań',
                'country' => 'PL',
                'phone' => '600500600',
                'email' => 'magazyn@example.com',
                'point_id' => null,
                'point_label' => null,
            ],
            'cod' => [
                'amount' => 149.99,
                'type' => '1D',
                'return_type' => 'BA',
                'bank_account' => '61109010140000071219812874',
                'status' => null,
            ],
            'insurance' => 500.0,
            'pickup' => [
                'date' => '2026-10-08',
                'time_from' => '10:00',
                'time_to' => '16:00',
                'no_courier_order' => false,
                'multi_pickup' => false,
                'pickup_number' => null,
            ],
            'services' => ['SMS notification when dispatched', 'Another sender on the label'],
            'additional_fields' => ['external_transport_security' => 'YES'],
            'packs' => [
                ['type' => 'ST', 'length' => 40, 'width' => 30, 'height' => 20, 'weight' => 2.5, 'amount' => 2],
            ],
        ], $this->createReader()->read($request));
    }

    public function testTheCoverAddressReadsItsNumbersAsTheOthersDo(): void
    {
        $request = SdkSerializer::create()->encode(new CreateOrderMessage(new Order(
            'DPD',
            ShipmentTypeEnum::Box,
            [new PackItem(40, 30, 20, 2.5, PackTypeEnum::Standard, 1)],
            new Address(
                'Jan Kowalski',
                'Prosta',
                'Warszawa',
                '00-838',
                'jan@example.com',
                '600100200',
                null,
                '20',
                '5',
            ),
            new Address(
                'Anna Nowak',
                'Długa',
                'Kraków',
                '31-147',
                'anna@example.com',
                '600300400',
                null,
                '7',
            ),
            coverAddress: new Address(
                'Magazyn Północ',
                'Składowa',
                'Poznań',
                '61-897',
                'magazyn@example.com',
                '600500600',
                houseNumber: '3',
                flatNumber: '2',
            ),
        )));

        $read = $this->createReader()->read($request);

        self::assertSame('Prosta 20/5', $read['sender']['street'] ?? null);
        self::assertSame(
            'Składowa 3/2',
            $read['cover_address']['street'] ?? null,
            'Its house number and flat, sent in fields of their own, follow the street.',
        );
    }

    public function testWhatTheRequestLeftOutIsEmpty(): void
    {
        $read = $this->createReader()->read(
            '{"courier":"INPOST_PACZKOMAT","shipmenttype":"box","courierservice":{"SMS_NOTIFICATION_RECIPIENT":false}}',
        );

        self::assertSame('box', $read['shipment_type']);
        self::assertNull($read['description']);
        self::assertNull($read['sender']);
        self::assertNull($read['cover_address']);
        self::assertNull($read['cod']);
        self::assertNull($read['pickup']);
        self::assertSame([], $read['services'], 'A service sent as off is none.');
        self::assertSame([], $read['packs']);
    }

    public function testAnUnreadableRequestGivesNothing(): void
    {
        foreach (['', 'not json', '{"courier":"DPD",', 'null', '"create_order"'] as $request) {
            self::assertSame([], $this->createReader()->read($request), $request);
        }
    }

    private function createReader(): SentOrderReader
    {
        return new SentOrderReader(
            new Json(),
            new OptionLabelProvider(
                new ShipmentState(),
                new ShipmentType(),
                new PackType(),
                new CodType(),
                new CodReturnType(),
                new CodStatus(),
                new AdditionalService(),
            ),
        );
    }
}
