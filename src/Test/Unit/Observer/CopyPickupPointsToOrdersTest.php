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
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\QuotePoint;
use Polkurier\Shipping\Observer\CopyPickupPointsToOrders;
use Polkurier\Shipping\Observer\TagMultishippingOrder;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

#[CoversClass(CopyPickupPointsToOrders::class)]
#[CoversClass(TagMultishippingOrder::class)]
#[AllowMockObjectsWithoutExpectations]
class CopyPickupPointsToOrdersTest extends TestCase
{
    public function testEachOrderGetsThePointOfTheAddressItWasPlacedFor(): void
    {
        $copied = [];
        $store = $this->createStub(OrderPointStore::class);
        $store->method('getForQuote')->willReturnCallback(fn(int $quoteId, int $addressId): ?QuotePoint => match ($addressId) {
            11 => $this->point('INPOST_PACZKOMAT', 'WAW195M'),
            12 => $this->point('INPOST_PACZKOMAT', 'KRK01M'),
            default => null,
        });
        $store->method('copyToOrder')->willReturnCallback(function (QuotePoint $point, int $orderId) use (&$copied): OrderPointInterface {
            $copied[$orderId] = $point->getPointId();

            return $this->createStub(OrderPointInterface::class);
        });

        $orders = [
            $this->order(101, 11, 'polkurier_INPOST_PACZKOMAT'),
            $this->order(102, 12, 'flatrate_flatrate'),
            $this->order(103, 13, 'polkurier_DPD'),
        ];
        $quote = $this->createStub(Quote::class);
        $quote->method('getId')->willReturn(5);

        (new CopyPickupPointsToOrders($store, new NullLogger()))->execute(new Observer(['event' => new Event(['orders' => $orders, 'quote' => $quote])]));

        self::assertSame([101 => 'WAW195M'], $copied, 'Only where the order still uses the method the point was chosen for.');
    }

    public function testAFailingCopyIsLoggedAndTheOtherOrdersStillGetTheirPoints(): void
    {
        $copied = [];
        $store = $this->createStub(OrderPointStore::class);
        $store->method('getForQuote')->willReturnCallback(fn(int $quoteId, int $addressId): QuotePoint => $this->point('INPOST_PACZKOMAT', 'P' . $addressId));
        $store->method('copyToOrder')->willReturnCallback(function (QuotePoint $point, int $orderId) use (&$copied): OrderPointInterface {
            if ($orderId === 101) {
                throw new \RuntimeException('Deadlock found when trying to get lock');
            }
            $copied[$orderId] = $point->getPointId();

            return $this->createStub(OrderPointInterface::class);
        });
        $logger = new class extends AbstractLogger {
            /**
             * @var array<int, string>
             */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = $level . ': ' . $message;
            }
        };
        $quote = $this->createStub(Quote::class);
        $quote->method('getId')->willReturn(5);
        $orders = [$this->order(101, 11, 'polkurier_INPOST_PACZKOMAT'), $this->order(102, 12, 'polkurier_INPOST_PACZKOMAT')];

        (new CopyPickupPointsToOrders($store, $logger))->execute(new Observer(['event' => new Event(['orders' => $orders, 'quote' => $quote])]));

        self::assertSame([102 => 'P12'], $copied);
        self::assertSame(['error: Pickup point not copied to the order'], $logger->records);
    }

    private function order(int $id, int $addressId, string $method): Order
    {
        /** @var Order $order */
        $order = (new ObjectManager($this))->getObject(Order::class);
        $order->setEntityId($id)->setShippingMethod($method);
        $address = $this->createStub(Quote\Address::class);
        $address->method('getId')->willReturn($addressId);
        (new TagMultishippingOrder())->execute(new Observer(['event' => new Event(['order' => $order, 'address' => $address])]));

        return $order;
    }

    private function point(string $method, string $pointId): QuotePoint
    {
        /** @var QuotePoint $point */
        $point = (new ObjectManager($this))->getObject(QuotePoint::class);

        return $point->setMethodCode($method)->setPointId($pointId);
    }
}
