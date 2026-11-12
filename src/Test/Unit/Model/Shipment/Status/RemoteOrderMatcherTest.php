<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Status;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Shipment as MagentoShipment;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Enum\Config\ExternalOrderIdEnum;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sdk\ApplicationIdProvider;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderMatcher;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Builder\Order\GetOrdersMessageBuilderFactory;
use Polkurier\Webservice\Request\Order\GetOrdersMessage;
use Polkurier\Webservice\Response\Order as RemoteOrder;
use Polkurier\Webservice\Response\Order\OrderListMessage;
use Polkurier\Webservice\Testing\RecordingClient;

#[CoversClass(RemoteOrderMatcher::class)]
class RemoteOrderMatcherTest extends SdkTestCase
{
    /**
     * @var array<int, OrderListMessage|LocalizedException> what Polkurier answers, by page
     */
    private array $pages = [];

    /**
     * @var GetOrdersMessage[] what Polkurier was asked, in order
     */
    private array $asked = [];

    public function testTheNumberIsFoundOnALaterPage(): void
    {
        $this->pages = [
            1 => $this->createOrderListMessage(3, '18015-10011', '18015-10012'),
            2 => $this->createOrderListMessage(3, '18015-10013', '18015-1001'),
            3 => $this->createOrderListMessage(3, '18015-10014'),
        ];

        $order = $this->createMatcher()->findByNumber('18015-1001');

        self::assertSame('18015-1001', $order->getNumber());
        self::assertSame([1, 2], $this->collectAskedPages(), 'No page is read after the one it is on.');
        self::assertSame('18015-1001', $this->asked[0]->getSearchPhrase());
        self::assertSame(GetOrdersMessage::MAX_PAGE_SIZE, $this->asked[0]->getPageSize());
        self::assertTrue($this->asked[0]->isPacks() && $this->asked[0]->isItems(), 'Read with its parcels and items.');
    }

    public function testALongerNumberHoldingTheSearchedOneIsNotTakenForIt(): void
    {
        $this->pages = [1 => $this->createOrderListMessage(1, '18015-10011', '118015-1001')];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Polkurier has no order number 18015-1001 on this account.');

        $this->createMatcher()->findByNumber('18015-1001');
    }

    public function testAnOrderIsFoundByItsWaybill(): void
    {
        $this->pages = [1 => $this->list(['number' => '18015-10011', 'waybills' => [['number' => 'WB-10010']]], [
            'number' => '18015-1001',
            'waybills' => [['number' => 'WB-100'], ['number' => 'wb-1001']],
        ])];

        self::assertSame('18015-1001', $this->createMatcher()->findByWaybill('WB-1001')?->getNumber());
        self::assertSame('WB-1001', $this->asked[0]->getSearchPhrase());
    }

    public function testALiveOrderWithTheWaybillGoesBeforeACancelledOne(): void
    {
        $this->pages = [1 => $this->list(
            ['number' => '18015-1001', 'status_code' => 'A', 'waybills' => [['number' => 'W1']]],
            ['number' => '18015-1002', 'waybills' => [['number' => 'W1']]],
        )];

        self::assertSame('18015-1002', $this->createMatcher()->findByWaybill('W1')?->getNumber());
    }

    public function testAWaybillNoOrderHasFindsNothing(): void
    {
        $this->pages = [1 => $this->list(['number' => '18015-1001', 'waybills' => [['number' => 'W12']]])];

        self::assertNull($this->createMatcher()->findByWaybill('W1'));
    }

    public function testAnOrderWithoutAnInstallationsIdsMayBeAnyOrders(): void
    {
        self::assertTrue(
            $this->createMatcher()->isMadeFor($this->createMagentoOrder(), $this->createRemote(null, null)),
        );
    }

    public function testAnOrderWithThisInstallationsIdMustCarryTheOrdersNumber(): void
    {
        $matcher = $this->createMatcher();

        self::assertTrue(
            $matcher->isMadeFor($this->createMagentoOrder(), $this->createRemote('magento2-0123456789ab', '000000031')),
        );
        self::assertFalse(
            $matcher->isMadeFor($this->createMagentoOrder(), $this->createRemote('magento2-0123456789ab', '000000027')),
            'Its shipment\'s number may be another order\'s while the shop sends order numbers.',
        );
        self::assertFalse(
            $matcher->isMadeFor($this->createMagentoOrder(), $this->createRemote('magento2-0123456789ab', '000000032')),
        );
    }

    public function testWhileTheShopSendsShipmentNumbersOneOfItsShipmentsNumberWillDo(): void
    {
        $matcher = $this->createMatcher(ExternalOrderIdEnum::Shipment);

        self::assertTrue(
            $matcher->isMadeFor($this->createMagentoOrder(), $this->createRemote('magento2-0123456789ab', '000000027')),
        );
        self::assertTrue(
            $matcher->isMadeFor($this->createMagentoOrder(), $this->createRemote('magento2-0123456789ab', '000000031')),
        );
    }

    public function testAnOrderAnotherInstallationMadeIsNotTheOrders(): void
    {
        self::assertFalse(
            $this->createMatcher()->isMadeFor(
                $this->createMagentoOrder(),
                $this->createRemote('magento2-staging', '000000031'),
            ),
        );
    }

    public function testTheSearchStopsAtTheLastPage(): void
    {
        $this->pages = [
            1 => $this->createOrderListMessage(2, '18015-10011'),
            2 => $this->createOrderListMessage(2, '18015-10012'),
        ];

        try {
            $this->createMatcher()->findByNumber('18015-1001');
            self::fail('Not on any page.');
        } catch (LocalizedException $e) {
            self::assertSame('Polkurier has no order number 18015-1001 on this account.', $e->getMessage());
        }
        self::assertSame([1, 2], $this->collectAskedPages());
    }

    public function testAnAnswerWithoutAPageCountIsTakenAsTheOnlyPage(): void
    {
        $this->pages = [1 => $this->createOrderListMessage(0)];

        try {
            $this->createMatcher()->findByNumber('18015-1001');
            self::fail('Nothing listed.');
        } catch (LocalizedException $e) {
            self::assertSame('Polkurier has no order number 18015-1001 on this account.', $e->getMessage());
        }
        self::assertSame([1], $this->collectAskedPages());
    }

    public function testNoMoreThanTheMaximumOfPagesIsRead(): void
    {
        $this->pages = [];

        try {
            $this->createMatcher()->findByNumber('18015-1001');
            self::fail('Not on the pages read.');
        } catch (LocalizedException $e) {
            self::assertSame('Polkurier has no order number 18015-1001 on this account.', $e->getMessage());
        }
        self::assertSame(
            \range(1, RemoteOrderMatcher::MAX_PAGES),
            $this->collectAskedPages(),
            'Polkurier says there are 50 pages.',
        );
    }

    public function testSeveralOrdersAreReadFromOneListOfTheShopsOrders(): void
    {
        $this->pages = [1 => $this->createOrderListMessage(1, '18015-1001', '18015-1009', '18015-1002', '18015-1003')];

        $orders = $this->createMatcher()->findByNumbers([
            $this->createShipment('18015-1001', '2026-10-05 08:00:00'),
            $this->createShipment('18015-1002', '2026-10-01 23:30:00'),
            $this->createShipment('18015-1003', '2026-10-03 12:00:00'),
        ]);

        self::assertSame(['18015-1001', '18015-1002', '18015-1003'], \array_keys($orders));
        self::assertSame('18015-1002', $orders['18015-1002']->getNumber());
        self::assertCount(1, $this->asked);
        $asked = $this->asked[0];
        self::assertSame('magento2-0123456789ab', $asked->getExternalApplicationId());
        self::assertNull($asked->getExternalOrderId());
        self::assertNull($asked->getSearchPhrase());
        self::assertSame('2026-09-30 23:30', $asked->getDateFrom()?->format('Y-m-d H:i'), 'A day before the first.');
        self::assertSame('2026-10-06 08:00', $asked->getDateTo()?->format('Y-m-d H:i'), 'A day after the last.');
        self::assertSame(GetOrdersMessage::MAX_PAGE_SIZE, $asked->getPageSize());
        self::assertTrue($asked->isPacks() && $asked->isItems(), 'Read as by its number.');
    }

    public function testTheListIsReadUntilEveryOrderIsFound(): void
    {
        $this->pages = [
            1 => $this->createOrderListMessage(3, '18015-1001', '18015-1002'),
            2 => $this->createOrderListMessage(3, '18015-1003', '18015-1004'),
        ];

        $orders = $this->createMatcher()->findByNumbers(
            $this->createShipments('18015-1001', '18015-1002', '18015-1003', '18015-1004'),
        );

        self::assertCount(4, $orders);
        self::assertSame([1, 2], $this->collectAskedPages(), 'No page is read after the last one needed.');
    }

    public function testOrdersNotListedAreLeftOut(): void
    {
        $this->pages = [
            1 => $this->createOrderListMessage(2, '18015-1001'),
            2 => $this->createOrderListMessage(2, '18015-1002'),
        ];

        $orders = $this->createMatcher()->findByNumbers(
            $this->createShipments('18015-1001', '18015-1002', '18015-1003'),
        );

        self::assertSame(['18015-1001', '18015-1002'], \array_keys($orders));
        self::assertSame([1, 2], $this->collectAskedPages());
    }

    public function testAListAsLongAsReadingOneByOneEndsAtItsFirstPage(): void
    {
        $this->pages = [
            1 => $this->createOrderListMessage(3, '18015-1001'),
            2 => $this->createOrderListMessage(3, '18015-1002'),
        ];

        $orders = $this->createMatcher()->findByNumbers(
            $this->createShipments('18015-1001', '18015-1002', '18015-1003'),
        );

        self::assertSame(['18015-1001'], \array_keys($orders), 'What the first page has is kept.');
        self::assertSame([1], $this->collectAskedPages(), 'Three pages for three orders.');
    }

    public function testASingleOrderIsNotListed(): void
    {
        self::assertSame([], $this->createMatcher()->findByNumbers($this->createShipments('18015-1001')));
        self::assertSame([], $this->asked, 'It is read by its number.');
    }

    public function testAPageThatCannotBeListedEndsTheList(): void
    {
        $this->pages = [
            1 => $this->createOrderListMessage(2, '18015-1001'),
            2 => new LocalizedException(__('Polkurier is not answering.')),
        ];

        $orders = $this->createMatcher()->findByNumbers(
            $this->createShipments('18015-1001', '18015-1002', '18015-1003'),
        );

        self::assertSame(['18015-1001'], \array_keys($orders), 'The rest are read by number.');
    }

    private function createMatcher(
        ExternalOrderIdEnum $externalOrderId = ExternalOrderIdEnum::Order,
    ): RemoteOrderMatcher
    {
        $gateway = $this->createStub(ShipmentGateway::class);
        $gateway->method('findOrders')->willReturnCallback(function (GetOrdersMessage $message): OrderListMessage {
            $this->asked[] = $message;
            $page = $this->pages[(int)$message->getPage()] ?? $this->createOrderListMessage(50, '18015-10011');

            return $page instanceof LocalizedException ? throw $page : $page;
        });
        $applicationId = $this->createStub(ApplicationIdProvider::class);
        $applicationId->method('get')->willReturn('magento2-0123456789ab');
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getExternalOrderId')->willReturn($externalOrderId);

        return new RemoteOrderMatcher(
            $gateway,
            $this->createStub(ShipmentFinder::class),
            new FixedDateTime((int)\strtotime('2026-10-06 10:00:00 UTC')),
            $applicationId,
            NewingObjectManager::createFactory(GetOrdersMessageBuilderFactory::class),
            $config,
        );
    }

    private function createShipment(string $number, string $createdAt = '2026-10-06 09:00:00'): ShipmentInterface
    {
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getPolkurierNumber')->willReturn($number);
        $shipment->method('getCreatedAt')->willReturn($createdAt);

        return $shipment;
    }

    /**
     * @return ShipmentInterface[]
     */
    private function createShipments(string ...$numbers): array
    {
        return \array_map(fn(string $number): ShipmentInterface => $this->createShipment($number), $numbers);
    }

    /**
     * One page of the list as the SDK reads it from Polkurier's answer.
     */
    private function createOrderListMessage(int $totalPages, string ...$numbers): OrderListMessage
    {
        $answer = \json_decode(self::loadFixture('get_orders.json'), true);
        $order = $answer['response']['result'][0];
        $answer['response']['totalpages'] = $totalPages;
        $answer['response']['result'] = \array_map(
            static fn(string $number): array => ['number' => $number] + $order,
            $numbers,
        );
        $http = new RecordingClient((string)\json_encode($answer));

        return $this->createClientPool($http)->getOrders()->getOrders(new GetOrdersMessage(searchPhrase: '18015-1001'));
    }

    /**
     * One page of the list with these orders, each the fixture's order with the fields given.
     *
     * @param array<string, mixed> ...$orders
     */
    private function list(array ...$orders): OrderListMessage
    {
        $answer = \json_decode(self::loadFixture('get_orders.json'), true);
        $order = $answer['response']['result'][0];
        $answer['response']['totalpages'] = 1;
        $answer['response']['result'] = \array_map(static fn(array $fields): array => $fields + $order, $orders);
        $http = new RecordingClient((string)\json_encode($answer));

        return $this->createClientPool($http)->getOrders()->getOrders(new GetOrdersMessage(searchPhrase: 'W1'));
    }

    private function createRemote(?string $applicationId, ?string $externalOrderId): RemoteOrder
    {
        $order = $this->createStub(RemoteOrder::class);
        $order->method('getExternalApplicationId')->willReturn($applicationId);
        $order->method('getExternalOrderId')->willReturn($externalOrderId);

        return $order;
    }

    /**
     * Order 000000031, with Magento shipment 000000027.
     */
    private function createMagentoOrder(): MagentoOrder
    {
        $shipment = $this->createStub(MagentoShipment::class);
        $shipment->method('getIncrementId')->willReturn('000000027');
        $order = $this->createStub(MagentoOrder::class);
        $order->method('getIncrementId')->willReturn('000000031');
        $order->method('getShipmentsCollection')->willReturn([$shipment]);

        return $order;
    }

    /**
     * @return int[]
     */
    private function collectAskedPages(): array
    {
        return \array_map(static fn(GetOrdersMessage $message): int => (int)$message->getPage(), $this->asked);
    }
}
