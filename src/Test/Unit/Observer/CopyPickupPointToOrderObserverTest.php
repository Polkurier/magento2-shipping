<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\Data\QuotePointInterface;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Observer\CopyPickupPointToOrderObserver;
use Psr\Log\LoggerInterface;

#[CoversClass(CopyPickupPointToOrderObserver::class)]
#[AllowMockObjectsWithoutExpectations]
class CopyPickupPointToOrderObserverTest extends TestCase
{
    /**
     * @var array<int, int> the order each point was copied to
     */
    private array $copied = [];

    /**
     * @var string[]
     */
    private array $errors = [];

    public function testThePointGoesWithTheOrderPlacedWithItsMethod(): void
    {
        $this->observer('INPOST_PACZKOMAT')->execute($this->event('polkurier_INPOST_PACZKOMAT'));

        self::assertSame([42], $this->copied);
    }

    public function testAPointOfAnotherMethodStaysBehind(): void
    {
        $this->observer('INPOST_PACZKOMAT')->execute($this->event('polkurier_DPD'));
        $this->observer('INPOST_PACZKOMAT')->execute($this->event('flatrate_flatrate'));

        self::assertSame([], $this->copied);
    }

    public function testWithoutAPointNothingIsCopied(): void
    {
        $this->observer(null)->execute($this->event('polkurier_DPD'));

        self::assertSame([], $this->copied);
    }

    public function testAFailureIsLoggedNotShownToTheCustomer(): void
    {
        $this->observer('INPOST_PACZKOMAT', true)->execute($this->event('polkurier_INPOST_PACZKOMAT'));

        self::assertSame(['Pickup point not copied to the order'], $this->errors);
    }

    private function event(string $method): Observer
    {
        $quote = $this->createStub(Quote::class);
        $quote->method('getId')->willReturn(7);
        /** @var Order $order */
        $order = (new ObjectManager($this))->getObject(Order::class);
        $order->setEntityId(42)->setShippingMethod($method);

        return new Observer(['event' => new Event(['quote' => $quote, 'order' => $order])]);
    }

    private function observer(?string $pointMethod, bool $failing = false): CopyPickupPointToOrderObserver
    {
        $store = $this->createStub(OrderPointStore::class);
        $store->method('getForQuote')->willReturnCallback(function () use ($pointMethod): ?QuotePointInterface {
            if ($pointMethod === null) {
                return null;
            }
            $point = $this->createStub(QuotePointInterface::class);
            $point->method('getMethodCode')->willReturn($pointMethod);

            return $point;
        });
        $store->method('copyToOrder')->willReturnCallback(function (QuotePointInterface $point, int $orderId) use ($failing): OrderPointInterface {
            if ($failing) {
                throw new \RuntimeException('Deadlock');
            }
            $this->copied[] = $orderId;

            return $this->createStub(OrderPointInterface::class);
        });
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function (string|\Stringable $message): void {
            $this->errors[] = (string)$message;
        });

        return new CopyPickupPointToOrderObserver($store, $logger);
    }
}
