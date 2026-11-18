<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Native;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Api\Data\OrderStatusHistoryInterface;
use Magento\Sales\Api\Data\ShipmentCommentInterfaceFactory;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Api\ShipmentCommentRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface as MagentoShipmentRepository;
use Magento\Sales\Helper\Data as SalesData;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Api\ShippingChoiceRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\ShippingChoice;

#[CoversMethod(NativeFollowUpService::class, 'notePlaced')]
#[AllowMockObjectsWithoutExpectations]
class PlacedNoteTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $notes = [];

    public function testAPlacedOrderIsNotedWithItsCarrierAndWaybills(): void
    {
        $this->followUp(null)->notePlaced($this->createOrder(), $this->createPlacedShipment(['WB-1', 'WB-2']));

        self::assertSame(['Polkurier order 24680-36 placed. Carrier: DPD. Waybills: WB-1, WB-2.'], $this->notes);
    }

    public function testTheCustomersChoiceIsNotedOnlyWhenTheOrderWentWithAnother(): void
    {
        $this->followUp('UPS Access Point, pickup point U98125654')
            ->notePlaced($this->createOrder(), $this->createPlacedShipment([]));

        self::assertSame([
            'Polkurier order 24680-36 placed. Carrier: DPD. Waybills: not issued yet. '
            . 'Customer\'s choice: UPS Access Point, pickup point U98125654.',
        ], $this->notes);
    }

    public function testEachWaybillLinksToTheTrackingPage(): void
    {
        $shipment = $this->createPlacedShipment(['WB-1', 'WB-2'])->setTrackingUrl(
            'https://polkurier.pl/sledzenie?nr=24680-36&x=1',
        );

        $this->followUp(null)->notePlaced($this->createOrder(), $shipment);

        self::assertSame([
            'Polkurier order 24680-36 placed. Carrier: DPD. Waybills: '
            . '<a href="https://polkurier.pl/sledzenie?nr=24680-36&amp;x=1" class="polkurier-external" '
            . 'target="_blank" rel="noopener">WB-1</a>, '
            . '<a href="https://polkurier.pl/sledzenie?nr=24680-36&amp;x=1" class="polkurier-external" '
            . 'target="_blank" rel="noopener">WB-2</a>.',
        ], $this->notes);
    }

    public function testAnOrderLinkedByHandIsNotedAsLinked(): void
    {
        $this->followUp(null)->notePlaced($this->createOrder(), $this->createPlacedShipment(['WB-1']), true);

        self::assertSame(['Linked with Polkurier order 24680-36. Carrier: DPD. Waybills: WB-1.'], $this->notes);
    }

    /**
     * @param string|null $choice the customer's choice kept aside, when the order's shipping was changed
     */
    private function followUp(?string $choice): NativeFollowUpService
    {
        $choices = $this->createStub(ShippingChoiceRepositoryInterface::class);
        $choices->method('getForOrder')->willReturn(
            $choice === null ? null : new ShippingChoice(['order_id' => 15, 'shipping_description' => $choice]),
        );
        $carrierNames = $this->createStub(CarrierNameProvider::class);
        $carrierNames->method('get')->willReturn('DPD');

        return new NativeFollowUpService(
            $this->createStub(OrderLoader::class),
            $this->createStub(ShipmentRepositoryInterface::class),
            $this->createStub(NativeShipmentService::class),
            $this->createStub(MagentoShipmentRepository::class),
            $this->createStub(ShipmentFinder::class),
            $this->createStub(OrderStatusHistoryRepositoryInterface::class),
            $this->createStub(ShipmentCommentInterfaceFactory::class),
            $this->createStub(ShipmentCommentRepositoryInterface::class),
            $this->createStub(ApiEventDispatcher::class),
            $this->createStub(Logger::class),
            $carrierNames,
            $this->createStub(ShipmentSender::class),
            $this->createStub(SalesData::class),
            $this->createStub(ScopeConfigInterface::class),
            $choices,
        );
    }

    private function createOrder(): Order
    {
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()
            ->onlyMethods(['addCommentToStatusHistory'])->getMock();
        $order->setData('entity_id', 15);
        $order->method('addCommentToStatusHistory')->willReturnCallback(
            function (string $comment): OrderStatusHistoryInterface {
                $this->notes[] = $comment;

                return $this->createStub(OrderStatusHistoryInterface::class);
            },
        );

        return $order;
    }

    /**
     * @param string[] $waybills
     */
    private function createPlacedShipment(array $waybills): Shipment
    {
        $shipment = (new ObjectManager($this))->getObject(Shipment::class, ['json' => new Json()]);
        $shipment->setState(ShipmentInterface::STATE_CREATED)
            ->setPolkurierNumber('24680-36')
            ->setCarrierCode('DPD')
            ->setWaybills($waybills);

        return $shipment;
    }
}
