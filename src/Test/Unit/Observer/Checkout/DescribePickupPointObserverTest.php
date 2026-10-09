<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Observer\Checkout;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\QuotePointInterface;
use Polkurier\Shipping\Api\QuotePointRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\PickupPoint\PointDescriptionBuilder;
use Polkurier\Shipping\Observer\Checkout\DescribePickupPointObserver;

#[CoversClass(DescribePickupPointObserver::class)]
#[CoversClass(PointDescriptionBuilder::class)]
#[AllowMockObjectsWithoutExpectations]
class DescribePickupPointObserverTest extends TestCase
{
    /**
     * @var array<int, int> quote address ids the point was asked for
     */
    private array $askedFor = [];

    public function testThePointGoesIntoTheShippingDescription(): void
    {
        $order = $this->order('polkurier_INPOST_PACZKOMAT');

        $this->observer($this->point('INPOST_PACZKOMAT', 'WAW01A', 'ul. Prosta 1, 00-001 Warszawa'))
            ->execute($this->event($order));

        self::assertSame(
            'Paczkomat InPost, pickup point WAW01A (ul. Prosta 1, 00-001 Warszawa)',
            $order->getShippingDescription(),
        );
        self::assertSame([0], $this->askedFor);
    }

    public function testEachMultiAddressOrderGetsItsAddressPoint(): void
    {
        $order = $this->order('polkurier_INPOST_PACZKOMAT');
        $address = (new ObjectManager($this))->getObject(Address::class);
        $address->setId(12);

        $this->observer($this->point('INPOST_PACZKOMAT', 'KRA02B', null))->execute($this->event($order, $address));

        self::assertSame('Paczkomat InPost, pickup point KRA02B', $order->getShippingDescription());
        self::assertSame([12], $this->askedFor);
    }

    public function testAPointOfAnotherMethodOrAnotherCarriersOrderChangesNothing(): void
    {
        $locker = $this->order('polkurier_INPOST_PACZKOMAT');
        $this->observer($this->point('DPD_POINT', 'WAW01A', null))->execute($this->event($locker));
        $flatRate = $this->order('flatrate_flatrate');
        $this->observer($this->point('INPOST_PACZKOMAT', 'WAW01A', null))->execute($this->event($flatRate));

        self::assertSame(
            ['Paczkomat InPost', 'Paczkomat InPost'],
            [$locker->getShippingDescription(), $flatRate->getShippingDescription()],
        );
    }

    public function testAnAddressTooLongForTheColumnIsLeftOutButNotThePoint(): void
    {
        $text = (new PointDescriptionBuilder())->describe('Paczkomat InPost', 'WAW01A', \str_repeat('x', 300));

        self::assertSame('Paczkomat InPost, pickup point WAW01A', $text);
    }

    private function observer(QuotePointInterface $point): DescribePickupPointObserver
    {
        $store = $this->createStub(QuotePointRepositoryInterface::class);
        $store->method('getForQuote')
            ->willReturnCallback(function (int $quoteId, int $addressId) use ($point): QuotePointInterface {
                $this->askedFor[] = $addressId;

                return $point;
            });

        return new DescribePickupPointObserver(
            $store,
            new PointDescriptionBuilder(),
            $this->createStub(Logger::class),
        );
    }

    private function point(string $method, string $id, ?string $label): QuotePointInterface
    {
        $point = $this->createStub(QuotePointInterface::class);
        $point->method('getMethodCode')->willReturn($method);
        $point->method('getPointId')->willReturn($id);
        $point->method('getPointLabel')->willReturn($label);

        return $point;
    }

    private function order(string $method): Order
    {
        /** @var Order $order */
        $order = (new ObjectManager($this))->getObject(Order::class);

        return $order->setShippingMethod($method)->setShippingDescription('Paczkomat InPost');
    }

    private function event(Order $order, ?Address $address = null): Observer
    {
        $quote = $this->createStub(Quote::class);
        $quote->method('getId')->willReturn(7);

        return new Observer(['event' => new Event(['order' => $order, 'quote' => $quote, 'address' => $address])]);
    }
}
