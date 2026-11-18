<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\ViewModel\Adminhtml;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\Data\ShippingChoiceInterface;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Api\ShippingChoiceRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Details\OptionLabelProvider;
use Polkurier\Shipping\Model\Shipment\Details\SentOrderReader;
use Polkurier\Shipping\Model\Shipment\Screen\ScreenDataProvider;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\ViewModel\Adminhtml\ActualShipping;
use Polkurier\Webservice\Dto\Response\CourierPoint;

#[CoversClass(ActualShipping::class)]
#[AllowMockObjectsWithoutExpectations]
class ActualShippingTest extends TestCase
{
    /**
     * @var Shipment[] the order's placed Polkurier shipments
     */
    private array $placed = [];

    public function testEachCarrierAndPointTheOrderWentWithIsListedOnceSayingWhichDiffer(): void
    {
        $this->placed = [
            $this->createShipment('INPOST_PACZKOMAT', 'KRA235M'),
            $this->createShipment('DHL_POINT', '4515215'),
            $this->createShipment('DHL_POINT', '4515215'),
        ];

        self::assertSame([
            [
                'carrier' => 'INPOST_PACZKOMAT name',
                'point' => 'KRA235M',
                'point_label' => 'As chosen at checkout',
                'changed' => false,
            ],
            [
                'carrier' => 'DHL_POINT name',
                'point' => '4515215',
                'point_label' => 'Kraków, Długa 1',
                'changed' => true,
            ],
        ], $this->createViewModel()->getForOrder($this->createOrder('polkurier_INPOST_PACZKOMAT')));
    }

    public function testAnotherCarriersOrderShowsNothing(): void
    {
        $this->placed = [$this->createShipment('DPD', '')];

        self::assertSame([], $this->createViewModel()->getForOrder($this->createOrder('flatrate_flatrate')));
    }

    public function testTheCustomersChoiceShowsOnlyWhenTheShippingWasChanged(): void
    {
        $choice = $this->createStub(ShippingChoiceInterface::class);
        $choice->method('getShippingDescription')->willReturn('Paczkomat InPost, pickup point KRA235M');
        $order = $this->createOrder('polkurier_DHL_POINT');

        self::assertSame(
            'Paczkomat InPost, pickup point KRA235M',
            $this->createViewModel($choice)->getCustomerChoice($order),
        );
        self::assertNull($this->createViewModel()->getCustomerChoice($order));
    }

    private function createOrder(string $shippingMethod): Order
    {
        /** @var Order $order */
        $order = (new ObjectManager($this))->getObject(Order::class);

        return $order->setEntityId(15)->setShippingMethod($shippingMethod);
    }

    private function createShipment(string $carrier, string $point): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class, ['json' => new Json()]);

        return $shipment->setOrderId(15)
            ->setCarrierCode($carrier)
            ->setRequest((string)\json_encode(['recipient' => ['point_id' => $point]]));
    }

    private function createViewModel(?ShippingChoiceInterface $choice = null): ActualShipping
    {
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('findForOrder')->willReturnCallback(fn(): array => $this->placed);
        $names = $this->createStub(CarrierNameProvider::class);
        $names->method('get')->willReturnCallback(static fn(string $code): string => $code . ' name');
        $chosen = $this->createStub(OrderPointInterface::class);
        $chosen->method('getPointId')->willReturn('KRA235M');
        $chosen->method('getPointLabel')->willReturn('As chosen at checkout');
        $points = $this->createStub(OrderPointRepositoryInterface::class);
        $points->method('getForOrder')->willReturn($chosen);
        $screen = $this->createStub(ScreenDataProvider::class);
        $screen->method('findRecipientPoint')->willReturn(new CourierPoint('4515215', address: 'Kraków, Długa 1'));
        $choices = $this->createStub(ShippingChoiceRepositoryInterface::class);
        $choices->method('getForOrder')->willReturn($choice);

        return new ActualShipping(
            $finder,
            $names,
            $points,
            new SentOrderReader(new Json(), $this->createStub(OptionLabelProvider::class)),
            $screen,
            $choices,
        );
    }
}
