<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\OrderPoint;
use Polkurier\Shipping\Model\OrderPointEditor;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\CourierPoint;

#[CoversClass(OrderPointEditor::class)]
class OrderPointEditorTest extends TestCase
{
    private Order&Stub $order;
    private OrderPointStore&Stub $store;
    private CarrierCatalog&Stub $catalog;

    protected function setUp(): void
    {
        $this->order = $this->createStub(Order::class);
        $this->store = $this->createStub(OrderPointStore::class);
        $this->catalog = $this->createStub(CarrierCatalog::class);
        $this->catalog->method('getCarrier')->willReturn($this->createStub(Carrier::class));
    }

    public function testChangeIsNotedWithThePreviousAndTheNewPoint(): void
    {
        $this->store->method('getForOrder')->willReturn($this->point('WAW195M'));
        $found = $this->createStub(CourierPoint::class);
        $found->method('getAddress')->willReturn('01-381 Warszawa, Powstańców Śląskich 72');
        $found->method('isCod')->willReturn(true);
        $this->catalog->method('findPoint')->willReturn($found);

        $this->order = $this->createMock(Order::class);
        $this->order->expects(self::once())->method('addCommentToStatusHistory')
            ->with('Pickup point changed from WAW195M to WAW01M.');
        $point = $this->editor()->change(25, ' WAW01M ');

        self::assertSame('WAW01M', $point?->getPointId());
        self::assertSame('01-381 Warszawa, Powstańców Śląskich 72', $point->getPointLabel());
    }

    public function testUnknownPointIsRefusedAndNothingIsSaved(): void
    {
        $this->catalog->method('findPoint')->willReturn(null);

        $this->store = $this->createMock(OrderPointStore::class);
        $this->store->method('getForOrder')->willReturn($this->point('WAW195M'));
        $this->store->expects(self::never())->method('saveForOrder');
        $this->order = $this->createMock(Order::class);
        $this->order->expects(self::never())->method('addCommentToStatusHistory');
        $this->expectException(LocalizedException::class);
        $this->editor()->change(25, 'XXX999');
    }

    public function testPointRequiredByTheCarrierCannotBeRemoved(): void
    {

        $this->store = $this->createMock(OrderPointStore::class);
        $this->store->method('getForOrder')->willReturn($this->point('WAW195M'));
        $this->store->expects(self::never())->method('deleteForOrder');
        $this->expectException(LocalizedException::class);
        $this->editor(pointRequired: true)->change(25, '');
    }

    private function editor(bool $pointRequired = false): OrderPointEditor
    {
        $this->order->method('getShippingMethod')->willReturn('polkurier_INPOST_PACZKOMAT');
        $this->order->method('getStoreId')->willReturn(1);
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('get')->willReturn($this->order);
        $features = $this->createStub(CarrierFeatures::class);
        $features->method('isRecipientPointAllowed')->willReturn(true);
        $features->method('isRecipientPointRequired')->willReturn($pointRequired);
        $features->method('getPointProviders')->willReturn(['INPOST_PACZKOMAT']);

        return new OrderPointEditor($orders, $this->store, $this->catalog, $features, $this->createStub(ErrorMessages::class));
    }

    private function point(string $pointId): OrderPoint
    {
        // The model as loaded, without the resource it would be saved with.
        $point = (new \ReflectionClass(OrderPoint::class))->newInstanceWithoutConstructor();
        $point->setPointId($pointId);

        return $point;
    }
}
