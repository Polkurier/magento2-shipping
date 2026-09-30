<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Multishipping;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\InputException;
use Magento\Multishipping\Model\Checkout\Type\Multishipping;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Checkout\PointSelection;
use Polkurier\Shipping\Model\Checkout\MultishippingPoints;
use Polkurier\Shipping\Observer\CaptureMultishippingPoints;
use Polkurier\Shipping\Plugin\Multishipping\CheckoutPlugin;

#[CoversClass(CheckoutPlugin::class)]
#[CoversClass(CaptureMultishippingPoints::class)]
#[CoversClass(MultishippingPoints::class)]
#[AllowMockObjectsWithoutExpectations]
class CheckoutPluginTest extends TestCase
{
    /** @var array<int, array<int, mixed>> */
    private array $selected = [];
    /** @var array<int, array<int, mixed>> */
    private array $required = [];

    public function testEachAddressGetsItsOwnPointWithItsMethod(): void
    {
        $this->plugin(['11' => 'WAW195M'])->beforeSetShippingMethods(
            $this->checkout(),
            ['11' => 'polkurier_INPOST_PACZKOMAT', '12' => 'flatrate_flatrate'],
        );

        self::assertSame([
            ['polkurier', 'INPOST_PACZKOMAT', 'WAW195M', 11],
            ['flatrate_flatrate', '', null, 12],
        ], $this->selected, 'Another carrier clears the address\'s point.');
    }

    public function testEveryPolkurierAddressIsCheckedBeforeTheOrdersArePlaced(): void
    {
        $this->plugin([])->beforeCreateOrders($this->checkout());

        self::assertSame([['INPOST_PACZKOMAT', 11]], $this->required);
    }

    public function testAMissingPointStopsTheShippingStep(): void
    {
        $selection = $this->createStub(PointSelection::class);
        $selection->method('select')->willThrowException(new InputException(\__('Choose a pickup point for the selected delivery method.')));
        $this->expectException(InputException::class);
        (new CheckoutPlugin(new MultishippingPoints(), $selection))->beforeSetShippingMethods($this->checkout(), ['11' => 'polkurier_INPOST_PACZKOMAT']);
    }

    /**
     * @param array<int|string, string> $points
     */
    private function plugin(array $points): CheckoutPlugin
    {
        $posted = new MultishippingPoints();
        (new CaptureMultishippingPoints($posted))->execute(new Observer(['event' => new Event(['request' => $this->request($points)])]));
        $selection = $this->createStub(PointSelection::class);
        $selection->method('select')->willReturnCallback(function ($quote, string $carrier, string $method, ?string $point, int $addressId): void {
            $this->selected[] = [$carrier, $method, $point, $addressId];
        });
        $selection->method('requirePoint')->willReturnCallback(function ($quote, string $method, int $addressId): void {
            $this->required[] = [$method, $addressId];
        });

        return new CheckoutPlugin($posted, $selection);
    }

    /**
     * @param array<int|string, string> $points
     */
    private function request(array $points): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['pickup_point', [], $points]]);

        return $request;
    }

    private function checkout(): Multishipping
    {
        $addresses = [];
        foreach ([11 => 'polkurier_INPOST_PACZKOMAT', 12 => 'flatrate_flatrate'] as $id => $method) {
            $address = $this->createStub(Address::class);
            $address->method('getId')->willReturn($id);
            $address->method('getShippingMethod')->willReturn($method);
            $addresses[] = $address;
        }
        $quote = $this->createStub(Quote::class);
        $quote->method('getAllShippingAddresses')->willReturn($addresses);
        $checkout = $this->createStub(Multishipping::class);
        $checkout->method('getQuote')->willReturn($quote);

        return $checkout;
    }
}
