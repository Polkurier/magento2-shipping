<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Status;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Model\Sdk\ApplicationIdProvider;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderMatcher;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Builder\Order\GetOrdersMessageBuilderFactory;
use Polkurier\Webservice\Request\Order\GetOrdersMessage;
use Polkurier\Webservice\Response\Order\OrderListMessage;
use Polkurier\Webservice\Testing\RecordingClient;

#[CoversClass(RemoteOrderMatcher::class)]
class RemoteOrderMatcherTest extends SdkTestCase
{
    /**
     * @var array<int, OrderListMessage> what Polkurier answers, by page
     */
    private array $pages = [];

    /**
     * @var GetOrdersMessage[] what Polkurier was asked, in order
     */
    private array $asked = [];

    public function testTheNumberIsFoundOnALaterPage(): void
    {
        $this->pages = [
            1 => $this->page(3, '18015-10011', '18015-10012'),
            2 => $this->page(3, '18015-10013', '18015-1001'),
            3 => $this->page(3, '18015-10014'),
        ];

        $order = $this->matcher()->findByNumber('18015-1001');

        self::assertSame('18015-1001', $order->getNumber());
        self::assertSame([1, 2], $this->askedPages(), 'No page is read after the one it is on.');
        self::assertSame('18015-1001', $this->asked[0]->getSearchPhrase());
        self::assertSame(GetOrdersMessage::MAX_PAGE_SIZE, $this->asked[0]->getPageSize());
        self::assertTrue($this->asked[0]->isPacks() && $this->asked[0]->isItems(), 'Read with its parcels and items.');
    }

    public function testALongerNumberHoldingTheSearchedOneIsNotTakenForIt(): void
    {
        $this->pages = [1 => $this->page(1, '18015-10011', '118015-1001')];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Polkurier has no order number 18015-1001 on this account.');

        $this->matcher()->findByNumber('18015-1001');
    }

    public function testTheSearchStopsAtTheLastPage(): void
    {
        $this->pages = [1 => $this->page(2, '18015-10011'), 2 => $this->page(2, '18015-10012')];

        try {
            $this->matcher()->findByNumber('18015-1001');
            self::fail('Not on any page.');
        } catch (LocalizedException $e) {
            self::assertSame('Polkurier has no order number 18015-1001 on this account.', $e->getMessage());
        }
        self::assertSame([1, 2], $this->askedPages());
    }

    public function testAnAnswerWithoutAPageCountIsTakenAsTheOnlyPage(): void
    {
        $this->pages = [1 => $this->page(0)];

        try {
            $this->matcher()->findByNumber('18015-1001');
            self::fail('Nothing listed.');
        } catch (LocalizedException $e) {
            self::assertSame('Polkurier has no order number 18015-1001 on this account.', $e->getMessage());
        }
        self::assertSame([1], $this->askedPages());
    }

    public function testNoMoreThanTheMaximumOfPagesIsRead(): void
    {
        $this->pages = [];

        try {
            $this->matcher()->findByNumber('18015-1001');
            self::fail('Not on the pages read.');
        } catch (LocalizedException $e) {
            self::assertSame('Polkurier has no order number 18015-1001 on this account.', $e->getMessage());
        }
        self::assertSame(
            \range(1, RemoteOrderMatcher::MAX_PAGES),
            $this->askedPages(),
            'Polkurier says there are 50 pages.',
        );
    }

    private function matcher(): RemoteOrderMatcher
    {
        $gateway = $this->createStub(ShipmentGateway::class);
        $gateway->method('findOrders')->willReturnCallback(function (GetOrdersMessage $message): OrderListMessage {
            $this->asked[] = $message;

            return $this->pages[(int)$message->getPage()] ?? $this->page(50, '18015-10011');
        });

        return new RemoteOrderMatcher(
            $gateway,
            $this->createStub(ShipmentFinder::class),
            new FixedDateTime(),
            $this->createStub(ApplicationIdProvider::class),
            NewingObjectManager::factory(GetOrdersMessageBuilderFactory::class),
        );
    }

    /**
     * One page of the list as the SDK reads it from Polkurier's answer.
     */
    private function page(int $totalPages, string ...$numbers): OrderListMessage
    {
        $answer = \json_decode(self::fixture('get_orders.json'), true);
        $order = $answer['response']['result'][0];
        $answer['response']['totalpages'] = $totalPages;
        $answer['response']['result'] = \array_map(
            static fn(string $number): array => ['number' => $number] + $order,
            $numbers,
        );
        $http = new RecordingClient((string)\json_encode($answer));

        return $this->clientPool($http)->orders()->getOrders(new GetOrdersMessage(searchPhrase: '18015-1001'));
    }

    /**
     * @return int[]
     */
    private function askedPages(): array
    {
        return \array_map(static fn(GetOrdersMessage $message): int => (int)$message->getPage(), $this->asked);
    }
}
