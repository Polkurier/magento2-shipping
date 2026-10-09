<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\ViewModel\Adminhtml;

use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\PickupPoint\PointDescriptionBuilder;
use Polkurier\Shipping\ViewModel\Adminhtml\OrderPickupPoint;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Response\Carrier;

#[CoversClass(OrderPickupPoint::class)]
#[AllowMockObjectsWithoutExpectations]
class OrderPickupPointTest extends TestCase
{
    public function testAMethodThatDeliversToPointsOnlyShowsThePoint(): void
    {
        $point = $this->point();

        self::assertSame(
            $point,
            $this->viewModel($point, required: true)->getPoint($this->order('polkurier_INPOST_PACZKOMAT')),
        );
    }

    public function testAPointTheShippingDescriptionNamesIsNotShownTwice(): void
    {
        self::assertNull(
            $this->viewModel($this->point(), required: true)
                ->getPoint($this->order(
                    'polkurier_INPOST_PACZKOMAT',
                    'Paczkomat InPost, pickup point WAW195M (02-134 Warszawa)',
                )),
        );
    }

    public function testAMethodWherePointsAreOptionalShowsNone(): void
    {
        self::assertNull($this->viewModel($this->point(), required: false)->getPoint($this->order('polkurier_DPD')));
    }

    public function testAnOrderOfAnotherCarrierShowsNone(): void
    {
        $store = $this->createMock(OrderPointRepositoryInterface::class);
        $store->expects(self::never())->method('getForOrder');

        self::assertNull(
            (new OrderPickupPoint(
                $store,
                $this->createStub(CarrierCatalog::class),
                $this->createStub(CarrierFeatureResolver::class),
                new PointDescriptionBuilder(),
            ))->getPoint($this->order('flatrate_flatrate')),
        );
    }

    public function testWithoutTheCarrierListTheStoredPointIsShown(): void
    {
        $point = $this->point();
        $store = $this->createStub(OrderPointRepositoryInterface::class);
        $store->method('getForOrder')->willReturn($point);
        $catalog = $this->createStub(CarrierCatalog::class);
        $catalog->method('getCarrier')->willThrowException(new ConnectionException('Timed out.'));

        self::assertSame(
            $point,
            (new OrderPickupPoint(
                $store,
                $catalog,
                $this->createStub(CarrierFeatureResolver::class),
                new PointDescriptionBuilder(),
            ))->getPoint($this->order('polkurier_INPOST_PACZKOMAT')),
        );
    }

    private function viewModel(OrderPointInterface $point, bool $required): OrderPickupPoint
    {
        $store = $this->createStub(OrderPointRepositoryInterface::class);
        $store->method('getForOrder')->willReturn($point);
        $catalog = $this->createStub(CarrierCatalog::class);
        $catalog->method('getCarrier')->willReturn(new Carrier('INPOST_PACZKOMAT', 'Paczkomat', false, null));
        $features = $this->createStub(CarrierFeatureResolver::class);
        $features->method('isRecipientPointRequired')->willReturn($required);

        return new OrderPickupPoint($store, $catalog, $features, new PointDescriptionBuilder());
    }

    private function point(): OrderPointInterface
    {
        $point = $this->createStub(OrderPointInterface::class);
        $point->method('getPointId')->willReturn('WAW195M');

        return $point;
    }

    private function order(string $shippingMethod, string $description = 'Paczkomat InPost'): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn($shippingMethod);
        $order->method('getShippingDescription')->willReturn($description);
        $order->method('getEntityId')->willReturn(25);
        $order->method('getStoreId')->willReturn(1);

        return $order;
    }
}
