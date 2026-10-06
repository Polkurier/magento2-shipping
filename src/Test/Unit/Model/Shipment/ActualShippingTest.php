<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Attribute as SalesAttribute;
use Magento\Sales\Model\ResourceModel\GridInterface;
use Magento\Store\Model\App\Emulation;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Model\Checkout\PointDescription;
use Polkurier\Shipping\Model\OrderComments;
use Polkurier\Shipping\Model\OrderPoint;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\ActualShipping;
use Polkurier\Shipping\Model\Shipment\Details\SentOrder;
use Polkurier\Shipping\Model\Shipment\MagentoShipment;
use Polkurier\Shipping\Model\Shipment\ScreenContext;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\ShippingChoice;
use Polkurier\Shipping\Model\ShippingChoiceStore;
use Polkurier\Webservice\Response\CourierPoint;

#[CoversClass(ActualShipping::class)]
#[AllowMockObjectsWithoutExpectations]
class ActualShippingTest extends TestCase
{
    private Order $order;
    private ?ShippingChoice $choice = null;
    private ?OrderPointInterface $point = null;
    private ?Shipment $placed = null;

    /**
     * @var string[] what was written, in order
     */
    private array $log = [];

    protected function setUp(): void
    {
        $this->order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->onlyMethods(['getStoreId'])->getMock();
        $this->order->method('getStoreId')->willReturn(1);
        $this->order->setData(['entity_id' => 15, 'shipping_method' => 'polkurier_DHL_POINT', 'shipping_description' => 'DHL POP, punkt odbioru 4515215']);
        $this->point = $this->orderPoint('DHL_POINT', '4515215', 'Kraków, Kocmyrzowska 2b');
    }

    public function testAnotherCarrierAndPointBecomeTheOrdersAndTheCustomersChoiceIsKept(): void
    {
        $this->placed = $this->shipment('INPOST_PACZKOMAT', 'KRA235M');

        $this->service()->sync(15);

        self::assertSame('polkurier_INPOST_PACZKOMAT', $this->order->getShippingMethod());
        self::assertSame('Paczkomat InPost, pickup point KRA235M (31-870 Kraków, 2 Pułku Lotniczego 44)', $this->order->getShippingDescription());
        self::assertSame([
            'keep polkurier_DHL_POINT|DHL POP, punkt odbioru 4515215|4515215',
            'save shipping_method,shipping_description',
            'grid 15',
            'point INPOST_PACZKOMAT KRA235M 31-870 Kraków, 2 Pułku Lotniczego 44 cod',
            'comment',
        ], $this->log);
    }

    public function testShippingAsTheOrderSaysChangesNothing(): void
    {
        $this->placed = $this->shipment('DHL_POINT', '4515215');

        $this->service()->sync(15);

        self::assertSame([], $this->log);
    }

    public function testWithNoPlacedPolkurierOrderLeftTheCustomersChoiceComesBack(): void
    {
        $this->order->setShippingMethod('polkurier_INPOST_PACZKOMAT')->setShippingDescription('Paczkomat InPost, pickup point KRA235M');
        $this->choice = new ShippingChoice([
            'order_id' => 15, 'shipping_method' => 'polkurier_DHL_POINT', 'shipping_description' => 'DHL POP, punkt odbioru 4515215',
            'point_method_code' => 'DHL_POINT', 'point_id' => '4515215', 'point_label' => 'Kraków', 'point_cod' => 1,
        ]);

        $this->service()->sync(15);

        self::assertSame('polkurier_DHL_POINT', $this->order->getShippingMethod());
        self::assertSame('DHL POP, punkt odbioru 4515215', $this->order->getShippingDescription());
        self::assertSame(['save shipping_method,shipping_description', 'grid 15', 'point DHL_POINT 4515215 Kraków cod', 'forget', 'comment'], $this->log);
    }

    public function testACourierWithoutAPointLeavesTheOrderWithoutOne(): void
    {
        $this->placed = $this->shipment('DPD', '');

        $this->service()->sync(15);

        self::assertSame('polkurier_DPD', $this->order->getShippingMethod());
        self::assertSame('Kurier DPD', $this->order->getShippingDescription());
        self::assertContains('no point', $this->log);
    }

    private function service(): ActualShipping
    {
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('get')->willReturn($this->order);
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getItems')->willReturnCallback(fn(): array => $this->placed !== null ? [$this->placed] : []);
        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturn($collection);

        $choices = $this->createStub(ShippingChoiceStore::class);
        $choices->method('get')->willReturnCallback(fn(): ?ShippingChoice => $this->choice);
        $choices->method('keep')->willReturnCallback(function (int $orderId, ?string $method, ?string $description, ?OrderPointInterface $point): void {
            $this->log[] = 'keep ' . $method . '|' . $description . '|' . $point?->getPointId();
            $this->choice = new ShippingChoice(['shipping_method' => $method, 'shipping_description' => $description]);
        });
        $choices->method('delete')->willReturnCallback(function (): void {
            $this->log[] = 'forget';
        });
        $points = $this->createStub(OrderPointStore::class);
        $points->method('getForOrder')->willReturnCallback(fn(): ?OrderPointInterface => $this->point);
        $points->method('replaceForOrder')->willReturnCallback(function (int $orderId, string $method, string $pointId, ?string $label, bool $cod): void {
            $this->log[] = \trim("point $method $pointId $label" . ($cod ? ' cod' : ''));
        });
        $points->method('deleteForOrder')->willReturnCallback(function (): void {
            $this->log[] = 'no point';
        });

        $found = $this->createStub(CourierPoint::class);
        $found->method('getAddress')->willReturn('31-870 Kraków, 2 Pułku Lotniczego 44');
        $found->method('isCod')->willReturn(true);
        $context = $this->createStub(ScreenContext::class);
        $context->method('findRecipientPoint')->willReturn($found);
        $titles = $this->createStub(MagentoShipment::class);
        $titles->method('getTrackTitle')->willReturnCallback(static fn(Order $order, string $carrier): string => match ($carrier) {
            'INPOST_PACZKOMAT' => 'Paczkomat InPost',
            'DPD' => 'Kurier DPD',
            default => $carrier,
        });
        $attribute = $this->createStub(SalesAttribute::class);
        $attribute->method('saveAttribute')->willReturnCallback(function (Order $order, array $fields) use ($attribute): SalesAttribute {
            $this->log[] = 'save ' . \implode(',', $fields);

            return $attribute;
        });
        $grid = $this->createStub(GridInterface::class);
        $grid->method('refresh')->willReturnCallback(function (int $id): void {
            $this->log[] = 'grid ' . $id;
        });
        $comments = $this->createStub(OrderComments::class);
        $comments->method('add')->willReturnCallback(function (): void {
            $this->log[] = 'comment';
        });
        $sentOrder = $this->createStub(SentOrder::class);
        $sentOrder->method('read')->willReturnCallback(static fn(string $json): array => (array)\json_decode($json, true));

        return new ActualShipping(
            $orders,
            new ShipmentFinder($collections),
            $choices,
            $points,
            $sentOrder,
            $context,
            $titles,
            new PointDescription(),
            $this->createStub(Emulation::class),
            $attribute,
            $grid,
            $comments,
        );
    }

    private function shipment(string $carrier, string $point): Shipment
    {
        $shipment = $this->getMockBuilder(Shipment::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $shipment->setData([
            'carrier_code' => $carrier,
            'polkurier_number' => '18015-2877',
            'request' => (string)\json_encode(['recipient' => ['point_id' => $point]]),
        ]);

        return $shipment;
    }

    private function orderPoint(string $method, string $pointId, string $label): OrderPointInterface
    {
        $point = $this->getMockBuilder(OrderPoint::class)->disableOriginalConstructor()->onlyMethods([])->getMock();

        return $point->setMethodCode($method)->setPointId($pointId)->setPointLabel($label);
    }
}
