<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Adminhtml;

use Magento\Framework\AuthorizationInterface;
use Magento\Sales\Block\Adminhtml\Order\View;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Plugin\Adminhtml\OrderViewPlugin;
use Polkurier\Shipping\Plugin\Adminhtml\ShipButtonPositionPlugin;

#[CoversClass(OrderViewPlugin::class)]
#[AllowMockObjectsWithoutExpectations]
class OrderViewPluginTest extends TestCase
{
    public function testTheButtonShowsWhileAPolkurierOrderCanBePlaced(): void
    {
        self::assertSame([ShipButtonPositionPlugin::BUTTON], $this->collectButtons('polkurier_DPD', canPlace: true));
    }

    public function testWithNothingToPlaceAPolkurierOrderForThereIsNoButton(): void
    {
        self::assertSame([], $this->collectButtons('polkurier_DPD', canPlace: false));
    }

    public function testOrdersOfOtherCarriersGetNoButton(): void
    {
        self::assertSame([], $this->collectButtons('flatrate_flatrate', canPlace: true));
    }

    /**
     * @return string[] the ids of the buttons added
     */
    private function collectButtons(string $shippingMethod, bool $canPlace): array
    {
        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn($shippingMethod);
        $order->method('getId')->willReturn(29);
        $added = [];
        $view = $this->createStub(View::class);
        $view->method('getOrder')->willReturn($order);
        $view->method('getUrl')->willReturn('https://admin/polkurier/shipment/create');
        $view->method('addButton')->willReturnCallback(function (string $id) use (&$added, $view): View {
            $added[] = $id;

            return $view;
        });
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn(true);
        $service = $this->createStub(NativeShipmentService::class);
        $service->method('canPlaceFor')->willReturn($canPlace);

        (new OrderViewPlugin($authorization, $service))->beforeSetLayout($view, null);

        return $added;
    }
}
