<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Observer\Checkout;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\PickupPoint\PointSelectionRecorder;
use Polkurier\Shipping\Observer\Checkout\ValidatePickupPointObserver;

#[CoversClass(ValidatePickupPointObserver::class)]
#[AllowMockObjectsWithoutExpectations]
class ValidatePickupPointObserverTest extends TestCase
{
    public function testAPolkurierOrderIsCheckedForItsMethodsPoint(): void
    {
        $selection = $this->createMock(PointSelectionRecorder::class);
        $selection->expects(self::once())->method('requirePoint')->with(self::anything(), 'INPOST_PACZKOMAT');

        (new ValidatePickupPointObserver($selection))->execute($this->createEvent('polkurier_INPOST_PACZKOMAT'));
    }

    public function testOtherCarriersAndVirtualOrdersArentChecked(): void
    {
        $selection = $this->createMock(PointSelectionRecorder::class);
        $selection->expects(self::never())->method('requirePoint');

        (new ValidatePickupPointObserver($selection))->execute($this->createEvent('flatrate_flatrate'));
        (new ValidatePickupPointObserver($selection))->execute($this->createEvent('polkurier_INPOST_PACZKOMAT', true));
    }

    private function createEvent(string $method, bool $virtual = false): Observer
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingMethod'])
            ->getMock();
        $address->method('getShippingMethod')->willReturn($method);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'isVirtual'])
            ->getMock();
        $quote->method('getShippingAddress')->willReturn($address);
        $quote->method('isVirtual')->willReturn($virtual);

        return new Observer(['event' => new Event(['quote' => $quote])]);
    }
}
