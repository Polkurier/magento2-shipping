<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Adminhtml;

use Magento\Framework\AuthorizationInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use Magento\Shipping\Block\Adminhtml\View;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Create as ShipmentCreate;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Plugin\Adminhtml\ShipmentViewPlugin;

#[CoversClass(ShipmentViewPlugin::class)]
#[AllowMockObjectsWithoutExpectations]
class ShipmentViewPluginTest extends TestCase
{
    public function testAShipmentWithAPolkurierOrderLinksItsDetails(): void
    {
        self::assertSame([ShipmentViewPlugin::VIEW_BUTTON], $this->collectButtons(linked: true, free: false));
    }

    public function testAShipmentWithoutAnActivePolkurierOrderOffersToAddOne(): void
    {
        self::assertSame([ShipmentViewPlugin::ADD_BUTTON], $this->collectButtons(linked: false, free: true));
    }

    public function testAShipmentWhoseOrderWasCancelledLinksItAndOffersANewOne(): void
    {
        self::assertSame(
            [ShipmentViewPlugin::VIEW_BUTTON, ShipmentViewPlugin::ADD_BUTTON],
            $this->collectButtons(linked: true, free: true),
        );
    }

    public function testAnAdminWhoMayNotShipIsNotOfferedANewOrder(): void
    {
        self::assertSame(
            [],
            $this->collectButtons(linked: false, free: true, denied: [ShipmentCreate::ADMIN_RESOURCE]),
        );
    }

    public function testShipmentsOfOtherCarriersGetNoButton(): void
    {
        self::assertSame([], $this->collectButtons(linked: true, free: true, shippingMethod: 'flatrate_flatrate'));
    }

    /**
     * @param string[] $denied the ACL resources the admin hasn't
     *
     * @return string[] the ids of the buttons added
     */
    private function collectButtons(
        bool $linked,
        bool $free,
        array $denied = [],
        string $shippingMethod = 'polkurier_DPD',
    ): array {
        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn($shippingMethod);
        $order->method('getId')->willReturn(29);
        $magentoShipment = $this->createStub(MagentoShipmentModel::class);
        $magentoShipment->method('getId')->willReturn(52);
        $magentoShipment->method('getOrder')->willReturn($order);
        $added = [];
        $view = $this->createStub(View::class);
        $view->method('getShipment')->willReturn($magentoShipment);
        $view->method('getUrl')->willReturn('https://admin.example/polkurier/shipment/view');
        $view->method('addButton')->willReturnCallback(function (string $id) use (&$added, $view): View {
            $added[] = $id;

            return $view;
        });
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')
            ->willReturnCallback(static fn(string $resource): bool => !\in_array($resource, $denied, true));
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('findLatestLinkedTo')->willReturn($linked ? $this->createStub(ShipmentInterface::class) : null);
        $nativeShipments = $this->createStub(NativeShipmentService::class);
        $nativeShipments->method('getFreeShipments')
            ->willReturn($free ? [52 => ['increment_id' => '000000052', 'replaces' => null]] : []);

        (new ShipmentViewPlugin($authorization, $nativeShipments, $finder))->beforeSetLayout($view, null);

        return $added;
    }
}
