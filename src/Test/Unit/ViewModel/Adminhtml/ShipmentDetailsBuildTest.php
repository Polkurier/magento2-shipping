<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\ViewModel\Adminhtml;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\ShipmentInterface as MagentoShipment;
use Magento\Sales\Api\ShipmentRepositoryInterface as MagentoShipmentRepository;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\Config\Source\AdditionalService;
use Polkurier\Shipping\Model\Config\Source\CodReturnType;
use Polkurier\Shipping\Model\Config\Source\CodStatus;
use Polkurier\Shipping\Model\Config\Source\CodType;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\Config\Source\ShipmentState;
use Polkurier\Shipping\Model\Config\Source\ShipmentType;
use Polkurier\Shipping\Model\Shipment\Details\Formatter;
use Polkurier\Shipping\Model\Shipment\Details\OptionLabelProvider;
use Polkurier\Shipping\Model\Shipment\Details\SentOrderReader;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;
use Polkurier\Shipping\ViewModel\Adminhtml\ShipmentDetails;
use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;
use Polkurier\Webservice\Enum\Order\CodReturnTypeEnum;
use Polkurier\Webservice\Enum\Order\CodTypeEnum;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;
use Polkurier\Webservice\Request\Order\CodMessage;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Request\Order\OrderAddressMessage;
use Polkurier\Webservice\Request\Order\PackMessage;
use Polkurier\Webservice\Request\Order\PickupMessage;
use Polkurier\Webservice\Serializer\SerializerProvider;

/**
 * The details page as build() puts it together, of a shipment read back from Polkurier and of one
 * Polkurier hasn't been read for yet.
 */
#[CoversClass(ShipmentDetails::class)]
#[AllowMockObjectsWithoutExpectations]
class ShipmentDetailsBuildTest extends TestCase
{
    public function testAShipmentReadFromPolkurierIsShownAsPolkurierReportsIt(): void
    {
        $shipment = $this->createShipment(ShipmentInterface::STATE_CREATED, '24680-4', [
            'number' => '24680-4',
            'description' => 'Order #000000052',
            'carrier' => 'DPD',
            'carrier_name' => 'DPD Classic',
            'shipment_type' => 'box',
            'tracking_url' => 'https://tracktrace.dpd.com.pl/parcelDetails?p1=0000123456789',
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
                'point_id' => null,
                'point_label' => null,
            ],
            'cover_address' => null,
            'cod' => [
                'amount' => 149.99,
                'type' => 'S',
                'return_type' => 'BA',
                'bank_account' => '61109010140000071219812874',
                'status' => 'WAITING',
            ],
            'insurance' => 500.0,
            'pickup' => [
                'date' => '2026-10-08',
                'time_from' => '10:00',
                'time_to' => '16:00',
                'no_courier_order' => false,
                'multi_pickup' => false,
                'pickup_number' => 'DPD-PU-1',
            ],
            'services' => [],
            'additional_fields' => [],
            'packs' => [
                [
                    'type' => 'ST',
                    'length' => 40,
                    'width' => 30,
                    'height' => 20,
                    'weight' => 2.5,
                    'amount' => 1,
                    'price_net' => 14.5,
                    'price_gross' => 17.84,
                ],
            ],
            'items' => [
                [
                    'description' => 'Paczka standardowa',
                    'type' => 'SHIPMENT',
                    'amount' => 1,
                    'value_net' => 14.5,
                    'value_gross' => 17.84,
                ],
            ],
            'price_net' => 14.5,
            'price_gross' => 17.84,
            'unpaid_amount' => 0.0,
            'is_paid' => true,
            'has_invoice' => false,
        ], shipmentId: 31, lastError: null);

        $view = $this->createView(order: true)->build($shipment);

        self::assertSame('Shipment details 24680-4', $view['title']);
        self::assertSame(
            ['Values marked "as sent" are empty in Polkurier\'s copy of the order, and are shown as they were sent.'],
            $view['notes'],
        );
        self::assertSame('Read from Polkurier 2026-10-06T08:00:00+00:00.', $view['synced']);
        self::assertSame(
            ['Order and delivery', 'Sender and recipient', 'Services and handover'],
            \array_column($view['sections'], 'title'),
        );
        self::assertSame(
            ['Items shipped', 'Parcels', 'Cost', 'Carrier tracking'],
            \array_column($view['tables'], 'title'),
        );
        self::assertSame(
            ['label' => 'Number', 'value' => '24680-4', 'url' => '', 'as_sent' => false, 'external' => false],
            $this->findRow($view, 'Polkurier order', 'Number'),
        );
        self::assertSame('Placed', $this->findRow($view, 'Polkurier order', 'Order state')['value']);
        self::assertSame('DPD Classic', $this->findRow($view, 'Delivery', 'Carrier')['value']);
        self::assertSame(
            ['Carrier', 'Shipment type', 'Shipment description', 'Delivery status'],
            \array_slice(\array_column($view['sections'][0]['items'][1]['rows'], 'label'), 0, 4),
            'The delivery starts with who carries what.',
        );
        self::assertSame('17.84 PLN', $this->findRow($view, 'Polkurier order', 'Order value')['value']);
        self::assertSame('0.00 PLN', $this->findRow($view, 'Polkurier order', 'Amount to pay')['value']);
        self::assertSame('No', $this->findRow($view, 'Polkurier order', 'Invoiced')['value']);
        self::assertSame(
            [
                'label' => 'Magento order',
                'value' => '#000000052',
                'url' => 'https://shop.example.com/admin/sales/order/view/order_id/29/',
                'as_sent' => false,
                'external' => false,
            ],
            $this->findRow($view, 'Polkurier order', 'Magento order'),
        );
        self::assertSame(
            [
                'label' => 'Magento shipment',
                'value' => '#000000031',
                'url' => 'https://shop.example.com/admin/sales/shipment/view/shipment_id/31/',
                'as_sent' => false,
                'external' => false,
            ],
            $this->findRow($view, 'Polkurier order', 'Magento shipment'),
        );
        self::assertSame(
            [
                'label' => 'Waybill',
                'value' => '0000123456789',
                'url' => 'https://tracktrace.dpd.com.pl/parcelDetails?p1=0000123456789',
                'as_sent' => false,
                'external' => true,
            ],
            $this->findRow($view, 'Delivery', 'Waybill'),
        );
        self::assertSame('Sklep Sp. z o.o., Jan Kowalski', $this->findRow($view, 'Sender', 'Name')['value']);
        self::assertFalse($this->findRow($view, 'Sender', 'Name')['as_sent']);
        // Polkurier's copy leaves the recipient's point and the services empty: shown as sent.
        self::assertSame(
            ['label' => 'Pickup point', 'value' => 'KRA01M', 'url' => '', 'as_sent' => true, 'external' => false],
            $this->findRow($view, 'Recipient', 'Pickup point'),
        );
        self::assertSame(
            [
                'label' => 'Additional services',
                'value' => 'SMS notification when dispatched',
                'url' => '',
                'as_sent' => true,
                'external' => false,
            ],
            $this->findRow($view, 'Services', 'Additional services'),
        );
        self::assertSame('149.99 PLN', $this->findRow($view, 'Services', 'Cash on delivery')['value']);
        self::assertFalse($this->findRow($view, 'Services', 'Cash on delivery')['as_sent'], 'Polkurier\'s own value.');
        self::assertSame(
            'Bank account: 61109010140000071219812874',
            $this->findRow($view, 'Services', 'Paid out to')['value'],
        );
        self::assertSame('Awaiting collection', $this->findRow($view, 'Services', 'COD status')['value']);
        self::assertSame('DPD-PU-1', $this->findRow($view, 'Handover to the carrier', 'Collection number')['value']);
        self::assertSame(
            [['Standard', '40 × 30 × 20 cm', '2.5 kg', '1', '17.84 PLN', '2 × Mug']],
            $view['tables'][1]['rows'],
        );
        self::assertSame([['Mug', 'MUG-1', '2']], $view['tables'][0]['rows']);
    }

    public function testAShipmentPolkurierWasntReadForIsShownAsItWasSent(): void
    {
        $shipment = $this->createShipment(
            ShipmentInterface::STATE_UNKNOWN,
            null,
            [],
            shipmentId: null,
            lastError: 'Polkurier did not answer in time.',
        );

        $view = $this->createView(order: false)->build($shipment, ['The carrier\'s scans could not be read.']);

        self::assertSame('Shipment details #41', $view['title']);
        self::assertSame([
            'Polkurier has not been read for this order yet: shown as it was sent.',
            'Last error: Polkurier did not answer in time.',
        ], $view['notes']);
        self::assertSame(['The carrier\'s scans could not be read.'], $view['errors']);
        self::assertSame('', $view['synced']);
        self::assertSame(
            ['Order and delivery', 'Sender and recipient', 'Services and handover'],
            \array_column($view['sections'], 'title'),
        );
        self::assertSame(
            ['Items shipped', 'Parcels', 'Carrier tracking'],
            \array_column($view['tables'], 'title'),
            'No cost before Polkurier priced it.',
        );
        self::assertSame('—', $this->findRow($view, 'Polkurier order', 'Number')['value']);
        self::assertSame('Outcome unknown', $this->findRow($view, 'Polkurier order', 'Order state')['value']);
        self::assertSame(
            'DPD Classic',
            $this->findRow($view, 'Delivery', 'Carrier')['value'],
            'Named by the carrier settings.',
        );
        self::assertSame('Parcel', $this->findRow($view, 'Delivery', 'Shipment type')['value']);
        self::assertSame('Order #000000052', $this->findRow($view, 'Delivery', 'Shipment description')['value']);
        self::assertSame(
            ['label' => 'Magento order', 'value' => '#29', 'url' => '', 'as_sent' => false, 'external' => false],
            $this->findRow($view, 'Polkurier order', 'Magento order'),
            'An order that is gone links nowhere.',
        );
        self::assertSame(
            [
                'label' => 'Magento shipment',
                'value' => 'none yet',
                'url' => '',
                'as_sent' => false,
                'external' => false,
            ],
            $this->findRow($view, 'Polkurier order', 'Magento shipment'),
        );
        self::assertSame('Prosta 20/5', $this->findRow($view, 'Sender', 'Street')['value']);
        self::assertSame('KRA01M', $this->findRow($view, 'Recipient', 'Pickup point')['value']);
        self::assertFalse(
            $this->findRow($view, 'Recipient', 'Pickup point')['as_sent'],
            'All of it is as sent, which the note says.',
        );
        self::assertSame(
            'SMS notification when dispatched',
            $this->findRow($view, 'Services', 'Additional services')['value'],
        );
        self::assertSame('Standard time', $this->findRow($view, 'Services', 'Payout time')['value']);
        self::assertSame(
            '2026-10-08, 10:00–16:00',
            $this->findRow($view, 'Handover to the carrier', 'Collection date')['value'],
        );
        self::assertSame('There is no Polkurier order to follow.', $view['tables'][2]['empty']);
        self::assertNotContains(
            'Order value',
            \array_column($view['sections'][0]['items'][0]['rows'], 'label'),
            'Not known before Polkurier is read.',
        );
    }

    public function testParcelsDroppedOffShowWhereUnderTheHandover(): void
    {
        $dropOff = ['no_courier_order' => true, 'multi_pickup' => false, 'pickup_number' => 'DPD-PU-2'];
        $atPoint = $this->createView(
            order: true,
        )->build($this->createShipment(ShipmentInterface::STATE_CREATED, '24680-12', [
            'sender' => ['person' => 'Jan Kowalski', 'point_id' => 'PL63197', 'point_label' => 'Kiosk, Prosta 1'],
            'pickup' => $dropOff,
        ], shipmentId: 31, lastError: null));

        self::assertSame(
            'Dropped off at a point',
            $this->findRow($atPoint, 'Handover to the carrier', 'Handover')['value'],
        );
        self::assertSame(
            [
                'label' => 'Drop-off point',
                'value' => 'PL63197 — Kiosk, Prosta 1',
                'url' => '',
                'as_sent' => false,
                'external' => false,
            ],
            $this->findRow($atPoint, 'Handover to the carrier', 'Drop-off point'),
        );
        self::assertNotContains(
            'Pickup point',
            \array_column($atPoint['sections'][1]['items'][0]['rows'], 'label'),
            'The sender\'s point is where the parcels go, not a point of the sender\'s address.',
        );

        self::assertNotContains(
            'Collection number',
            \array_column($atPoint['sections'][2]['items'][1]['rows'], 'label'),
            'Nothing is collected.',
        );

        $anyPoint = $this->createView(
            order: true,
        )->build($this->createShipment(ShipmentInterface::STATE_CREATED, '24680-15', [
            'sender' => ['person' => 'Jan Kowalski', 'point_id' => null],
            'pickup' => $dropOff,
        ], shipmentId: 31, lastError: null));

        self::assertSame(
            'any of the carrier\'s points',
            $this->findRow($anyPoint, 'Handover to the carrier', 'Drop-off point')['value'],
        );
    }

    /**
     * The row labelled $label of the item titled $item, in whichever section.
     *
     * @param array<string, mixed> $view
     *
     * @return array<string, string|bool>
     */
    private function findRow(array $view, string $item, string $label): array
    {
        foreach ($view['sections'] as $section) {
            foreach ($section['items'] as $entry) {
                if ($entry['title'] !== $item) {
                    continue;
                }
                foreach ($entry['rows'] as $row) {
                    if ($row['label'] === $label) {
                        return $row;
                    }
                }
            }
        }
        self::fail(\sprintf('No "%s" row in "%s".', $label, $item));
    }

    /**
     * A shipment of order 29, sent with one standard DPD parcel holding two mugs.
     *
     * @param array<string, mixed> $details Polkurier's copy of the order; none until it's read
     */
    private function createShipment(
        string $state,
        ?string $number,
        array $details,
        ?int $shipmentId,
        ?string $lastError,
    ): ShipmentInterface {
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getId')->willReturn(41);
        $shipment->method('getOrderId')->willReturn(29);
        $shipment->method('getShipmentId')->willReturn($shipmentId);
        $shipment->method('getState')->willReturn($state);
        $shipment->method('getPolkurierNumber')->willReturn($number);
        $shipment->method('getCarrierCode')->willReturn('DPD');
        $shipment->method('getDescription')->willReturn('Order #000000052');
        $shipment->method('getWaybills')->willReturn($number === null ? [] : ['0000123456789']);
        $shipment->method('getStatusLabel')->willReturn($number === null ? null : 'Przyjęta w oddziale');
        $shipment->method('getCreatedAt')->willReturn('2026-10-05 09:15:00');
        $shipment->method('getDetails')->willReturn($details);
        $shipment->method('getDetailsSyncedAt')->willReturn($details === [] ? null : '2026-10-06 08:00:00');
        $shipment->method('getRequest')->willReturn($this->buildSentRequest());
        $shipment->method('getDraft')->willReturn([
            'items' => [['order_item_id' => 5, 'qty' => 2]],
            'packs' => [['items' => [['order_item_id' => 5, 'qty' => 2]]]],
        ]);
        $shipment->method('getTracking')->willReturn([]);
        $shipment->method('getLastError')->willReturn($lastError);

        return $shipment;
    }

    /**
     * The create_order request as the module stores it: the SDK's own serialization.
     */
    private function buildSentRequest(): string
    {
        $message = new CreateOrderMessage(
            'DPD',
            ShipmentTypeEnum::Box,
            [new PackMessage(40, 30, 20, 2.5, PackTypeEnum::Standard)],
            new OrderAddressMessage(
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
            new OrderAddressMessage(
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
            new CodMessage(149.99, '61109010140000071219812874', CodTypeEnum::Standard, CodReturnTypeEnum::BankAccount),
            500.0,
            null,
            new PickupMessage(
                new \DateTimeImmutable('2026-10-08'),
                new \DateTimeImmutable('2026-10-08 10:00'),
                new \DateTimeImmutable('2026-10-08 16:00'),
            ),
            [AdditionalServiceEnum::SmsOnDispatch],
            description: 'Order #000000052',
        );

        return (new SerializerProvider())->getSerializer()->serialize($message, 'json');
    }

    private function createView(bool $order): ShipmentDetails
    {
        $labels = new OptionLabelProvider(
            new ShipmentState(),
            new ShipmentType(),
            new PackType(),
            new CodType(),
            new CodReturnType(),
            new CodStatus(),
            new AdditionalService(),
        );

        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('formatDateTime')->willReturnCallback(
            static fn(mixed $date): string => (new \DateTimeImmutable((string)$date, new \DateTimeZone('UTC')))
                ->format(\DATE_ATOM),
        );
        $prices = $this->createStub(PriceCurrencyInterface::class);
        $prices->method('format')
            ->willReturnCallback(static fn(float $amount): string => \number_format($amount, 2, '.', '') . ' PLN');
        $locale = $this->createStub(ResolverInterface::class);
        $locale->method('getLocale')->willReturn('en_US');

        $names = $this->createStub(CarrierNameProvider::class);
        $names->method('get')->willReturnMap([['DPD', 'DPD Classic']]);

        $magentoOrder = $this->createStub(Order::class);
        $magentoOrder->method('getIncrementId')->willReturn('000000052');
        $orders = $this->createStub(OrderLoader::class);
        $orders->method('find')->willReturn($order ? $magentoOrder : null);
        $amounts = $this->createStub(OrderAmountCalculator::class);
        $amounts->method('getItems')->willReturn([5 => ['name' => 'Mug', 'sku' => 'MUG-1']]);

        $magentoShipment = $this->createStub(MagentoShipment::class);
        $magentoShipment->method('getIncrementId')->willReturn('000000031');
        $magentoShipments = $this->createStub(MagentoShipmentRepository::class);
        $magentoShipments->method('get')->willReturnCallback(static fn(int $id): MagentoShipment => $id === 31
            ? $magentoShipment
            : throw new NoSuchEntityException(__('The entity that was requested doesn\'t exist.')));

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static function (string $route, array $params = []): string {
            $path = $route;
            foreach ($params as $name => $value) {
                $path .= '/' . $name . '/' . $value;
            }

            return 'https://shop.example.com/admin/' . $path . '/';
        });

        return new ShipmentDetails(
            $labels,
            new Formatter($timezone, $prices, $locale),
            new SentOrderReader(new Json(), $labels),
            $names,
            $orders,
            $amounts,
            new PackType(),
            $magentoShipments,
            $url,
        );
    }
}
